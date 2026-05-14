<?php

namespace Fireline\Engine;

use Fireline\Cache\SafeCache;
use Fireline\Cache\ThreatCache;
use Fireline\Extract\RequestExtractor;
use Fireline\Extract\RequestField;
use Fireline\Normalize\Normalizer;
use Fireline\Replay\ReplayRecorder;
use Fireline\Scoring\Thresholds;

class WafEngine
{
    protected $config;
    protected $extractor;
    protected $fieldInspector;
    protected $normalizer;
    protected $thresholds;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaultConfig(), $this->loadConfig(), $config);
        $this->config = $this->validateConfig($this->config);
        $this->extractor = new RequestExtractor($this->config);
        $this->normalizer = new Normalizer();
        $this->thresholds = new Thresholds($this->config);
        $this->fieldInspector = new FieldInspector($this->thresholds);
    }

    public function inspectCurrentRequest(): Decision
    {
        if ($this->config['bypass_firewall']) {
            return $this->finalizeDecision(Decision::allow(new RequestContext([])));
        }

        $request = $this->extractor->capture();
        $context = new RequestContext($request);

        $limitResult = (new RequestLimits($this->config, $this->thresholds->blockThreshold()))->inspect($request);
        if ($limitResult !== null) {
            $context->addResult($limitResult);
            return $this->finalizeDecision(Decision::block($context, 'request_limit', $limitResult));
        }

        $legacyDecision = $this->inspectLegacyGuards($request, $context);
        if ($legacyDecision !== null) {
            return $this->finalizeDecision($legacyDecision);
        }

        foreach ($this->extractor->extractFields($request) as $field) {
            $result = $this->fieldInspector->inspect($request, $field, $this->normalizer->run($field->value()), true);
            if ($result === null) {
                continue;
            }

            $context->addResult($result);

            if ($result->score() >= $this->thresholds->blockThreshold()) {
                ThreatCache::remember($result->fingerprint());
                return $this->finalizeDecision(Decision::block($context, 'field_score_threshold', $result));
            }

            if ($result->score() <= $this->thresholds->safeCacheThreshold()) {
                SafeCache::remember($result->fingerprint());
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

            $result = $this->fieldInspector->inspect($request, $field, (string) ($stored['normalized'] ?? ''), false);
            if ($result === null) {
                continue;
            }

            $context->addResult($result);
        }

        return (new DecisionEngine($this->thresholds->blockThreshold()))->finalize($context);
    }

    protected function inspectLegacyGuards(array $request, RequestContext $context)
    {
        $ip = new IpGuard();
        if (!$ip->safe($request['ip'], $this->config)) {
            $context->addResult(new ScanResult(
                'ip',
                'ip',
                $this->thresholds->blockThreshold(),
                [],
                ['ip_guard' => $this->thresholds->blockThreshold()],
                '',
                (string) $request['ip'],
                (string) $request['ip']
            ));

            return Decision::block($context, 'ip');
        }

        $bots = new BotGuard();
        if (!$bots->safe($request['user_agent'], $this->config)) {
            $context->addResult(new ScanResult(
                'user_agent',
                'header',
                $this->thresholds->blockThreshold(),
                [],
                ['bot_guard' => $this->thresholds->blockThreshold()],
                '',
                $bots->found(),
                strtolower($bots->found())
            ));

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
            'max_fields' => 200,
            'max_headers' => 100,
            'max_header_length' => 8192,
            'max_body_length' => 1048576,
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

        foreach (['max_fields', 'max_headers', 'max_header_length', 'max_body_length'] as $key) {
            $configs[$key] = max(1, (int) ($configs[$key] ?? $this->defaultConfig()[$key]));
        }

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
