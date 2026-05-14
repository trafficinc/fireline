<?php

namespace Fireline\Cache;

use Fireline\Telemetry\RuleMetrics;

class ThreatCache
{
    protected static $memory = [];

    public static function remember(string $fingerprint): void
    {
        RuleMetrics::increment('cache.threat.write');
        if (function_exists('apcu_store')) {
            apcu_store('threat:' . $fingerprint, true, 600);
            return;
        }

        self::$memory[$fingerprint] = true;
    }

    public static function isKnownThreat(string $fingerprint): bool
    {
        if (function_exists('apcu_fetch')) {
            $hit = apcu_fetch('threat:' . $fingerprint) === true;
            $hit ? RuleMetrics::cacheHit('threat') : RuleMetrics::cacheMiss('threat');
            return $hit;
        }

        $hit = isset(self::$memory[$fingerprint]);
        $hit ? RuleMetrics::cacheHit('threat') : RuleMetrics::cacheMiss('threat');

        return $hit;
    }
}
