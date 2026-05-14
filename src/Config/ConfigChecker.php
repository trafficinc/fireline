<?php

namespace Fireline\Config;

class ConfigChecker
{
    protected $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?: dirname(__DIR__, 2);
    }

    public function check(array $config = []): array
    {
        $config = array_merge($this->defaults(), $this->loadConfig(), $config);
        $checks = [
            $this->paranoia($config),
            $this->positiveInteger('max_fields', $config),
            $this->positiveInteger('max_headers', $config),
            $this->positiveInteger('max_header_length', $config),
            $this->positiveInteger('max_body_length', $config),
            $this->positiveInteger('max_value_length', $config),
            $this->threshold('score_threshold', $config),
            $this->threshold('regex_threshold', $config),
            $this->threshold('safe_cache_threshold', $config),
            $this->storageDirectory(),
            $this->writablePath('replay_path', (string) ($config['replay_path'] ?? '')),
            $this->writablePath('log_file', $this->logPath()),
            $this->apcu(),
        ];

        return [
            'ok' => count(array_filter($checks, function (array $check): bool {
                return $check['status'] === 'error';
            })) === 0,
            'checks' => $checks,
        ];
    }

    protected function defaults(): array
    {
        return [
            'paranoia_level' => 'medium',
            'max_fields' => 200,
            'max_headers' => 100,
            'max_header_length' => 8192,
            'max_body_length' => 1048576,
            'max_value_length' => 8192,
            'replay_path' => $this->root . '/storage/replay/traffic.ndjson',
            'score_threshold' => null,
            'regex_threshold' => null,
            'safe_cache_threshold' => null,
        ];
    }

    protected function loadConfig(): array
    {
        $config = [];
        foreach ([$this->root . '/config/waf.php', $this->root . '/config.php'] as $file) {
            if (!is_readable($file)) {
                continue;
            }

            $loaded = require $file;
            if (is_array($loaded)) {
                $config = array_merge($config, $loaded);
            }
        }

        return $config;
    }

    protected function paranoia(array $config): array
    {
        $level = strtolower((string) ($config['paranoia_level'] ?? ''));
        $valid = in_array($level, ['low', 'medium', 'high', 'strict'], true);

        return [
            'name' => 'paranoia_level',
            'status' => $valid ? 'ok' : 'error',
            'message' => $valid ? 'Using ' . $level : 'Invalid paranoia level: ' . $level,
        ];
    }

    protected function threshold(string $key, array $config): array
    {
        $value = $config[$key] ?? null;
        if ($value === null) {
            return [
                'name' => $key,
                'status' => 'ok',
                'message' => 'Using paranoia level default',
            ];
        }

        $valid = is_numeric($value) && (int) $value > 0;

        return [
            'name' => $key,
            'status' => $valid ? 'ok' : 'error',
            'message' => $valid ? 'Override: ' . (int) $value : 'Must be a positive integer or null',
        ];
    }

    protected function positiveInteger(string $key, array $config): array
    {
        $value = $config[$key] ?? null;
        $valid = is_numeric($value) && (int) $value > 0;

        return [
            'name' => $key,
            'status' => $valid ? 'ok' : 'error',
            'message' => $valid ? 'Using ' . (int) $value : 'Must be a positive integer',
        ];
    }

    protected function writablePath(string $name, string $path): array
    {
        if ($path === '') {
            return [
                'name' => $name,
                'status' => 'error',
                'message' => 'Path is empty',
            ];
        }

        $dir = is_dir($path) ? $path : dirname($path);
        $existing = $dir;
        while (!is_dir($existing) && $existing !== dirname($existing)) {
            $existing = dirname($existing);
        }

        if (is_dir($dir) && is_writable($dir)) {
            return [
                'name' => $name,
                'status' => 'ok',
                'message' => $path,
            ];
        }

        if (is_dir($existing) && is_writable($existing)) {
            return [
                'name' => $name,
                'status' => 'ok',
                'message' => 'Will create under writable parent: ' . $existing,
            ];
        }

        return [
            'name' => $name,
            'status' => 'error',
            'message' => 'Directory is not writable: ' . $dir,
        ];
    }

    protected function storageDirectory(): array
    {
        $path = $this->root . '/storage';
        if (!is_dir($path)) {
            return [
                'name' => 'storage_dir',
                'status' => 'error',
                'message' => 'Missing storage directory: ' . $path,
            ];
        }

        return [
            'name' => 'storage_dir',
            'status' => is_writable($path) ? 'ok' : 'error',
            'message' => is_writable($path) ? $path : 'Directory is not writable: ' . $path,
        ];
    }

    protected function logPath(): string
    {
        return $this->root . '/storage/logs/fireline.log';
    }

    protected function apcu(): array
    {
        return [
            'name' => 'apcu',
            'status' => function_exists('apcu_fetch') ? 'ok' : 'warn',
            'message' => function_exists('apcu_fetch') ? 'APCu available' : 'APCu unavailable; using in-process fallback cache',
        ];
    }
}
