<?php

namespace Fireline\Cache;

class RouteModelCache
{
    protected static $models = [];

    public static function get(string $route)
    {
        return self::$models[$route] ?? null;
    }

    public static function put(string $route, array $model): void
    {
        self::$models[$route] = $model;
    }
}
