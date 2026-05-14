<?php

namespace Fireline\Learning;

use Fireline\Extract\RequestField;
use Fireline\Telemetry\RuleMetrics;

class RouteLearner
{
    protected static $models;

    public static function compare(string $route, RequestField $field, string $normalized): int
    {
        $model = self::modelFor($route, $field->name());
        if ($model === null) {
            return 0;
        }

        $score = 0;
        $length = strlen($normalized);

        if (isset($model['max_length']) && $length > (int) $model['max_length']) {
            $score += min(10, 3 + (int) floor(($length - (int) $model['max_length']) / 32));
            RuleMetrics::increment('route_model.max_length');
        }

        if (isset($model['min_length']) && $length < (int) $model['min_length']) {
            $score += 2;
            RuleMetrics::increment('route_model.min_length');
        }

        if (isset($model['avg_length'])) {
            $avg = max(1, (int) $model['avg_length']);
            $tolerance = max(16, $avg * 3);
            if ($length > $avg + $tolerance) {
                $score += 4;
                RuleMetrics::increment('route_model.avg_length');
            }
        }

        $type = strtolower((string) ($model['type'] ?? ''));
        if ($type !== '' && !self::matchesType($normalized, $type)) {
            $score += self::typeScore($type);
            RuleMetrics::increment('route_model.type.' . $type);
        }

        return $score;
    }

    public static function reset(): void
    {
        self::$models = null;
    }

    public static function useModels(array $models): void
    {
        self::$models = $models;
    }

    protected static function modelFor(string $route, string $field): ?array
    {
        $routes = self::models();
        $routeModel = $routes[$route] ?? null;
        if (!is_array($routeModel)) {
            return null;
        }

        $fields = isset($routeModel['fields']) && is_array($routeModel['fields'])
            ? $routeModel['fields']
            : $routeModel;

        return isset($fields[$field]) && is_array($fields[$field])
            ? $fields[$field]
            : null;
    }

    protected static function models(): array
    {
        if (self::$models !== null) {
            return self::$models;
        }

        $file = dirname(__DIR__, 2) . '/config/routes.php';
        if (!is_readable($file)) {
            self::$models = [];
            return self::$models;
        }

        $models = require $file;
        self::$models = is_array($models) ? $models : [];

        return self::$models;
    }

    protected static function matchesType(string $value, string $type): bool
    {
        if ($value === '') {
            return true;
        }

        switch ($type) {
            case 'alpha':
                return preg_match('/\A[a-z]+\z/i', $value) === 1;
            case 'alnum':
                return preg_match('/\A[a-z0-9]+\z/i', $value) === 1;
            case 'int':
            case 'integer':
                return preg_match('/\A-?\d+\z/', $value) === 1;
            case 'numeric':
                return is_numeric($value);
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'slug':
                return preg_match('/\A[a-z0-9][a-z0-9_-]*\z/i', $value) === 1;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'opaque':
            case 'text':
                return true;
            default:
                return true;
        }
    }

    protected static function typeScore(string $type): int
    {
        return in_array($type, ['alpha', 'alnum', 'int', 'integer', 'numeric', 'slug'], true) ? 12 : 6;
    }
}
