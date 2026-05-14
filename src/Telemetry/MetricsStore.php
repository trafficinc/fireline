<?php

namespace Fireline\Telemetry;

class MetricsStore
{
    public static function read(string $path): array
    {
        if (!is_readable($path)) {
            return RuleMetrics::snapshotFrom([], []);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return RuleMetrics::snapshotFrom([], []);
        }

        return RuleMetrics::snapshotFrom(
            is_array($decoded['counters'] ?? null) ? $decoded['counters'] : [],
            is_array($decoded['timings'] ?? null) ? $decoded['timings'] : []
        );
    }

    public static function write(string $path, array $snapshot): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $handle = fopen($path, 'c+');
        if (!$handle) {
            return false;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }

        rewind($handle);
        $existing = json_decode((string) stream_get_contents($handle), true);
        $merged = self::merge(
            is_array($existing) ? $existing : [],
            $snapshot
        );
        $encoded = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        ftruncate($handle, 0);
        rewind($handle);
        $written = is_string($encoded) && fwrite($handle, $encoded . PHP_EOL) !== false;
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $written;
    }

    public static function reset(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $empty = RuleMetrics::snapshotFrom([], []);
        $encoded = json_encode($empty, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) && file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) !== false;
    }

    public static function merge(array $existing, array $snapshot): array
    {
        $counters = is_array($existing['counters'] ?? null) ? $existing['counters'] : [];
        foreach ((array) ($snapshot['counters'] ?? []) as $name => $count) {
            $counters[(string) $name] = ($counters[(string) $name] ?? 0) + (int) $count;
        }

        $timings = is_array($existing['timings'] ?? null) ? $existing['timings'] : [];
        foreach ((array) ($snapshot['timings'] ?? []) as $name => $timing) {
            if (!is_array($timing)) {
                continue;
            }

            if (!isset($timings[$name]) || !is_array($timings[$name])) {
                $timings[$name] = [
                    'count' => 0,
                    'total_ms' => 0.0,
                    'max_ms' => 0.0,
                ];
            }

            $timings[$name]['count'] += (int) ($timing['count'] ?? 0);
            $timings[$name]['total_ms'] += (float) ($timing['total_ms'] ?? 0);
            $timings[$name]['max_ms'] = max((float) ($timings[$name]['max_ms'] ?? 0), (float) ($timing['max_ms'] ?? 0));
        }

        return RuleMetrics::snapshotFrom($counters, $timings);
    }
}
