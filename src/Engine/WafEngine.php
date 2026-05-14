<?php

namespace Fireline\Engine;

use Fireline\Cache\FingerprintCache;
use Fireline\Cache\SafeCache;
use Fireline\Cache\ThreatCache;
use Fireline\Extract\RequestExtractor;
use Fireline\Heuristics\EncodingHeuristics;
use Fireline\Heuristics\EntropyHeuristics;
use Fireline\Heuristics\ShellHeuristics;
use Fireline\Heuristics\SqlHeuristics;
use Fireline\Heuristics\XssHeuristics;
use Fireline\Learning\RouteLearner;
use Fireline\Normalize\Normalizer;
use Fireline\Scan\AhoCorasick;
use Fireline\Scan\Prefilter;
use Fireline\Scan\RegexScanner;
use Fireline\Scoring\ScoreAccumulator;
use Fireline\Scoring\Thresholds;

class WafEngine
{
    protected $config;
    protected $extractor;
    protected $normalizer;
    protected $thresholds;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaultConfig(), $this->loadConfig(), $config);
        $this->config = $this->validateConfig($this->config);
        $this->extractor = new RequestExtractor($this->config);
        $this->normalizer = new Normalizer();
        $this->thresholds = new Thresholds($this->config);
    }

    public function inspectCurrentRequest(): Decision
    {
        if ($this->config['bypass_firewall']) {
            return Decision::allow(new RequestContext([]));
        }

        $request = $this->extractor->capture();
        $context = new RequestContext($request);

        $legacyDecision = $this->inspectLegacyGuards($request, $context);
        if ($legacyDecision !== null) {
            return $legacyDecision;
        }

        foreach ($this->extractor->extractFields($request) as $field) {
            $normalized = $this->normalizer->run($field->value());
            $fingerprint = FingerprintCache::build($request, $field, $normalized);

            if (SafeCache::isKnownSafe($fingerprint)) {
                continue;
            }

            $score = new ScoreAccumulator();
            $score->add('prefilter', Prefilter::analyze($normalized));

            $matches = AhoCorasick::scan($normalized);
            foreach ($matches as $match) {
                $score->addRule($match);
            }

            $score->add('sql_heuristics', SqlHeuristics::analyze($normalized));
            $score->add('xss_heuristics', XssHeuristics::analyze($normalized));
            $score->add('shell_heuristics', ShellHeuristics::analyze($normalized));
            $score->add('encoding_heuristics', EncodingHeuristics::analyze($normalized));
            $score->add('entropy_heuristics', EntropyHeuristics::analyze($normalized));

            if ($score->total() >= $this->thresholds->regexThreshold()) {
                $score->add('regex', RegexScanner::scan($normalized, $matches));
            }

            $score->add('route_model', RouteLearner::compare($request['route'], $field, $normalized));

            $result = [
                'field' => $field->name(),
                'source' => $field->source(),
                'score' => $score->total(),
                'matches' => $matches,
                'breakdown' => $score->breakdown(),
                'fingerprint' => $fingerprint,
                'value' => $field->value(),
                'normalized' => $normalized,
            ];

            $context->addResult($result);

            if ($score->total() >= $this->thresholds->blockThreshold()) {
                ThreatCache::remember($fingerprint);
                return Decision::block($context, 'field_score_threshold', $result);
            }

            if ($score->total() <= $this->thresholds->safeCacheThreshold()) {
                SafeCache::remember($fingerprint);
            }
        }

        return (new DecisionEngine($this->thresholds->blockThreshold()))->finalize($context);
    }

    protected function inspectLegacyGuards(array $request, RequestContext $context)
    {
        $ip = new IpGuard();
        if (!$ip->safe($request['ip'], $this->config)) {
            $context->addResult([
                'field' => 'ip',
                'source' => 'ip',
                'score' => $this->thresholds->blockThreshold(),
                'matches' => [],
                'value' => $request['ip'],
                'normalized' => $request['ip'],
            ]);

            return Decision::block($context, 'ip');
        }

        $bots = new BotGuard();
        if (!$bots->safe($request['user_agent'], $this->config)) {
            $context->addResult([
                'field' => 'user_agent',
                'source' => 'header',
                'score' => $this->thresholds->blockThreshold(),
                'matches' => [],
                'value' => $bots->found(),
                'normalized' => strtolower($bots->found()),
            ]);

            return Decision::block($context, 'bot');
        }

        return null;
    }

    protected function defaultConfig(): array
    {
        return [
            'bypass_firewall' => false,
            'strict_mode' => false,
            'ip_by_country' => false,
            'whitelist' => false,
            'trusted_proxies' => [],
            'max_value_length' => 8192,
            'inspect_json' => true,
            'inspect_headers' => true,
            'inspect_raw_body' => true,
            'score_threshold' => 25,
            'regex_threshold' => 10,
            'safe_cache_threshold' => 3,
        ];
    }

    protected function loadConfig(): array
    {
        $root = dirname(__DIR__, 2);
        $configs = [];
        foreach ([$root . '/config/waf.php', $root . '/config.php'] as $file) {
            if (is_readable($file)) {
                $loaded = require $file;
                if (is_array($loaded)) {
                    $configs = array_merge($configs, $loaded);
                }
            }
        }

        return $configs;
    }

    protected function validateConfig(array $configs): array
    {
        foreach (['bypass_firewall', 'strict_mode', 'ip_by_country', 'whitelist', 'inspect_json', 'inspect_headers', 'inspect_raw_body'] as $key) {
            $configs[$key] = filter_var($configs[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        if (!is_array($configs['trusted_proxies'] ?? null)) {
            $configs['trusted_proxies'] = [];
        }

        foreach (['max_value_length', 'score_threshold', 'regex_threshold', 'safe_cache_threshold'] as $key) {
            $configs[$key] = max(1, (int) ($configs[$key] ?? $this->defaultConfig()[$key]));
        }

        return $configs;
    }
}
