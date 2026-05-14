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
        return [
            'counters' => self::$counters,
            'timings' => self::$timings,
            'cache_hit_ratios' => self::cacheHitRatios(),
            'slowest_rules' => self::slowestRules(),
        ];
    }

    protected static function cacheHitRatios(): array
    {
        $ratios = [];
        foreach (self::$counters as $name => $count) {
            if (substr($name, -4) !== '.hit') {
                continue;
            }

            $cache = substr($name, 6, -4);
            $misses = self::$counters['cache.' . $cache . '.miss'] ?? 0;
            $total = $count + $misses;
            $ratios[$cache] = $total > 0 ? $count / $total : 0.0;
        }

        return $ratios;
    }

    protected static function slowestRules(): array
    {
        $timings = self::$timings;
        uasort($timings, function (array $a, array $b) {
            return $b['max_ms'] <=> $a['max_ms'];
        });

        return $timings;
    }
}
