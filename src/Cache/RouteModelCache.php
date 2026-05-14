<?php

namespace Fireline\Cache;

use Fireline\Telemetry\RuleMetrics;

class RouteModelCache
{
    protected static $models = [];
    protected static $prefix = 'route_model:';

    public static function get(string $route)
    {
        if (function_exists('apcu_fetch')) {
            $success = false;
            $model = apcu_fetch(self::$prefix . sha1($route), $success);
            $success ? RuleMetrics::cacheHit('route_model') : RuleMetrics::cacheMiss('route_model');
            return $success && is_array($model) ? $model : null;
        }

        $hit = isset(self::$models[$route]);
        $hit ? RuleMetrics::cacheHit('route_model') : RuleMetrics::cacheMiss('route_model');

        return $hit ? self::$models[$route] : null;
    }

    public static function put(string $route, array $model): void
    {
        RuleMetrics::increment('cache.route_model.write');
        if (function_exists('apcu_store')) {
            apcu_store(self::$prefix . sha1($route), $model, 300);
            return;
        }

        self::$models[$route] = $model;
    }

    public static function reset(): void
    {
        self::$models = [];
    }
}
