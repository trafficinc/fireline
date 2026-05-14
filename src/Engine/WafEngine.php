<?php

namespace Fireline\Engine;

use Fireline\Cache\FingerprintCache;
use Fireline\Cache\SafeCache;
use Fireline\Cache\ThreatCache;
use Fireline\Extract\RequestExtractor;
use Fireline\Extract\RequestField;
use Fireline\Heuristics\EncodingHeuristics;
use Fireline\Heuristics\EntropyHeuristics;
use Fireline\Heuristics\ShellHeuristics;
use Fireline\Heuristics\SqlHeuristics;
use Fireline\Heuristics\XssHeuristics;
use Fireline\Learning\RouteLearner;
use Fireline\Normalize\Normalizer;
use Fireline\Replay\ReplayRecorder;
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
            return $this->finalizeDecision(Decision::allow(new RequestContext([])));
        }

        $request = $this->extractor->capture();
        $context = new RequestContext($request);

        $legacyDecision = $this->inspectLegacyGuards($request, $context);
        if ($legacyDecision !== null) {
            return $this->finalizeDecision($legacyDecision);
        }

        foreach ($this->extractor->extractFields($request) as $field) {
            $result = $this->inspectField($request, $field, $this->normalizer->run($field->value()), true);
            if ($result === null) {
                continue;
            }

            $context->addResult($result);

            if ($result['score'] >= $this->thresholds->blockThreshold()) {
                ThreatCache::remember($result['fingerprint']);
                return $this->finalizeDecision(Decision::block($context, 'field_score_threshold', $result));
            }

            if ($result['score'] <= $this->thresholds->safeCacheThreshold()) {
                SafeCache::remember($result['fingerprint']);
            }
        }

        return $this->finalizeDecision((new DecisionEngine($this->thresholds->blockThreshold()))->finalize($context));
    }

    public function inspectReplayEvent(array $event): Decision
    {
        $request = is_array($event['request'] ?? null) ? $event['request'] : [];
        $request = array_merge([
            'ip' => '',
            'headers' => [],
            'method' => '',
            'route' => '',
            'uri' => '',
            'user_agent' => '',
            'referer' => '',
            'query_string' => '',
            'body' => '',
            'fields' => [],
        ], $request);
        $context = new RequestContext($request);

        foreach ((array) ($event['results'] ?? []) as $stored) {
            if (!is_array($stored)) {
                continue;
            }

            $field = new RequestField(
                (string) ($stored['field'] ?? ''),
                (string) ($stored['value'] ?? $stored['normalized'] ?? ''),
                (string) ($stored['source'] ?? 'replay')
            );

            $result = $this->inspectField($request, $field, (string) ($stored['normalized'] ?? ''), false);
            if ($result === null) {
                continue;
            }

            $context->addResult($result);
        }

        return (new DecisionEngine($this->thresholds->blockThreshold()))->finalize($context);
    }

    protected function inspectField(array $request, RequestField $field, string $normalized, bool $useSafeCache): ?array
    {
        $fingerprint = FingerprintCache::build($request, $field, $normalized);

        if ($useSafeCache && SafeCache::isKnownSafe($fingerprint)) {
            return null;
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

        $score->add('route_model', RouteLearner::compare((string) ($request['route'] ?? ''), $field, $normalized));

        return [
            'field' => $field->name(),
            'source' => $field->source(),
            'score' => $score->total(),
            'matches' => $matches,
            'breakdown' => $score->breakdown(),
            'fingerprint' => $fingerprint,
            'value' => $field->value(),
            'normalized' => $normalized,
        ];
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
            'paranoia_level' => 'medium',
            'replay_enabled' => false,
            'replay_path' => dirname(__DIR__, 2) . '/storage/replay/traffic.ndjson',
            'score_threshold' => null,
            'regex_threshold' => null,
            'safe_cache_threshold' => null,
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
        foreach (['bypass_firewall', 'strict_mode', 'ip_by_country', 'whitelist', 'inspect_json', 'inspect_headers', 'inspect_raw_body', 'replay_enabled'] as $key) {
            $configs[$key] = filter_var($configs[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        if (!is_array($configs['trusted_proxies'] ?? null)) {
            $configs['trusted_proxies'] = [];
        }

        $configs['max_value_length'] = max(1, (int) ($configs['max_value_length'] ?? $this->defaultConfig()['max_value_length']));

        foreach (['score_threshold', 'regex_threshold', 'safe_cache_threshold'] as $key) {
            if ($configs[$key] !== null) {
                $configs[$key] = max(1, (int) $configs[$key]);
            }
        }

        $configs['paranoia_level'] = strtolower((string) ($configs['paranoia_level'] ?? 'medium'));
        if (!in_array($configs['paranoia_level'], ['low', 'medium', 'high', 'strict'], true)) {
            $configs['paranoia_level'] = 'medium';
        }

        $configs['replay_path'] = (string) ($configs['replay_path'] ?? $this->defaultConfig()['replay_path']);

        return $configs;
    }

    protected function finalizeDecision(Decision $decision): Decision
    {
        if ($this->config['replay_enabled']) {
            (new ReplayRecorder($this->config['replay_path']))->record($decision);
        }

        return $decision;
    }
}
