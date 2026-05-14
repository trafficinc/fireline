<?php

namespace Fireline\Cache;

use Fireline\Telemetry\RuleMetrics;

class SafeCache
{
    protected static $memory = [];
    protected static $prefix = 'safe:';

    public static function remember(string $fingerprint): void
    {
        RuleMetrics::increment('cache.safe.write');
        if (function_exists('apcu_store')) {
            apcu_store(self::$prefix . $fingerprint, true, 300);
            return;
        }

        self::$memory[$fingerprint] = true;
    }

    public static function isKnownSafe(string $fingerprint): bool
    {
        if (function_exists('apcu_fetch')) {
            $hit = apcu_fetch(self::$prefix . $fingerprint) === true;
            $hit ? RuleMetrics::cacheHit('safe') : RuleMetrics::cacheMiss('safe');
            return $hit;
        }

        $hit = isset(self::$memory[$fingerprint]);
        $hit ? RuleMetrics::cacheHit('safe') : RuleMetrics::cacheMiss('safe');

        return $hit;
    }

    public static function reset(): void
    {
        self::$memory = [];
    }
}
