<?php

namespace Fireline\Telemetry;

class RuleMetrics
{
    protected static $enabled = true;
    protected static $counters = [];
    protected static $timings = [];

    public static function enable(bool $enabled = true): void
    {
        self::$enabled = $enabled;
    }

    public static function reset(): void
    {
        self::$counters = [];
        self::$timings = [];
    }

    public static function increment(string $name, int $amount = 1): void
    {
        if (!self::$enabled) {
            return;
        }

        self::$counters[$name] = (self::$counters[$name] ?? 0) + $amount;
    }

    public static function timing(string $name, float $milliseconds): void
    {
        if (!self::$enabled) {
            return;
        }

        if (!isset(self::$timings[$name])) {
            self::$timings[$name] = [
                'count' => 0,
                'total_ms' => 0.0,
                'max_ms' => 0.0,
            ];
        }

        self::$timings[$name]['count']++;
        self::$timings[$name]['total_ms'] += $milliseconds;
        self::$timings[$name]['max_ms'] = max(self::$timings[$name]['max_ms'], $milliseconds);
    }

    public static function cacheHit(string $cache): void
    {
        self::increment('cache.' . $cache . '.hit');
    }

    public static function cacheMiss(string $cache): void
    {
        self::increment('cache.' . $cache . '.miss');
    }

    public static function falsePositive(string $ruleId): void
    {
        self::increment('rule.' . $ruleId . '.false_positive');
    }

    public static function snapshot(): array
    {
        return self::snapshotFrom(self::$counters, self::$timings);
    }

    public static function snapshotFrom(array $counters, array $timings): array
    {
        $counters = self::normalizeCounters($counters);
        $timings = self::normalizeTimings($timings);

        return [
            'counters' => $counters,
            'timings' => $timings,
            'cache_hit_ratios' => self::cacheHitRatiosFrom($counters),
            'slowest_rules' => self::slowestRulesFrom($timings),
        ];
    }

    protected static function normalizeCounters(array $counters): array
    {
        $normalized = [];
        foreach ($counters as $name => $count) {
            $normalized[(string) $name] = (int) $count;
        }

        return $normalized;
    }

    protected static function normalizeTimings(array $timings): array
    {
        $normalized = [];
        foreach ($timings as $name => $timing) {
            if (!is_array($timing)) {
                continue;
            }

            $normalized[(string) $name] = [
                'count' => max(0, (int) ($timing['count'] ?? 0)),
                'total_ms' => max(0.0, (float) ($timing['total_ms'] ?? 0)),
                'max_ms' => max(0.0, (float) ($timing['max_ms'] ?? 0)),
            ];
        }

        return $normalized;
    }

    protected static function cacheHitRatios(): array
    {
        return self::cacheHitRatiosFrom(self::$counters);
    }

    protected static function cacheHitRatiosFrom(array $counters): array
    {
        $ratios = [];
        foreach ($counters as $name => $count) {
            if (substr($name, -4) !== '.hit') {
                continue;
            }

            $cache = substr($name, 6, -4);
            $misses = $counters['cache.' . $cache . '.miss'] ?? 0;
            $total = $count + $misses;
            $ratios[$cache] = $total > 0 ? $count / $total : 0.0;
        }

        return $ratios;
    }

    protected static function slowestRules(): array
    {
        return self::slowestRulesFrom(self::$timings);
    }

    protected static function slowestRulesFrom(array $timings): array
    {
        uasort($timings, function (array $a, array $b) {
            return ((float) ($b['max_ms'] ?? 0)) <=> ((float) ($a['max_ms'] ?? 0));
        });

        return $timings;
    }
}
