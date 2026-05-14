<?php

namespace Fireline\Learning;

class BaselineBuilder
{
    public static function build(array $events, int $minSamples = 3): array
    {
        $routes = [];

        foreach ($events as $event) {
            if (!is_array($event) || (bool) ($event['decision']['blocked'] ?? false)) {
                continue;
            }

            $route = (string) ($event['request']['route'] ?? '');
            if ($route === '') {
                continue;
            }

            foreach ((array) ($event['results'] ?? []) as $result) {
                if (!is_array($result)) {
                    continue;
                }

                $field = (string) ($result['field'] ?? '');
                $value = (string) ($result['normalized'] ?? '');
                if ($field === '') {
                    continue;
                }

                if (!isset($routes[$route][$field])) {
                    $routes[$route][$field] = [];
                }

                $routes[$route][$field][] = $value;
            }
        }

        $models = [];
        foreach ($routes as $route => $fields) {
            foreach ($fields as $field => $values) {
                if (count($values) < $minSamples) {
                    continue;
                }

                $models[$route]['fields'][$field] = self::fieldModel($values);
            }
        }

        return $models;
    }

    public static function buildFromReplayFile(string $path, int $minSamples = 3): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $events = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $event = json_decode($line, true);
            if (is_array($event)) {
                $events[] = $event;
            }
        }

        fclose($handle);

        return self::build($events, $minSamples);
    }

    protected static function fieldModel(array $values): array
    {
        $lengths = array_map('strlen', $values);
        sort($lengths);

        $model = [
            'type' => self::inferType($values),
            'min_length' => min($lengths),
            'max_length' => self::percentile($lengths, 0.95),
            'avg_length' => (int) round(array_sum($lengths) / count($lengths)),
            'allowed_chars' => self::inferAllowedChars($values),
        ];

        $shape = self::dominantShape($values);
        if ($shape !== null) {
            $model['shape'] = $shape;
        }

        return $model;
    }

    protected static function inferType(array $values): string
    {
        if (self::allMatch($values, '/\A-?\d+\z/')) {
            return 'int';
        }

        if (self::allMatch($values, '/\A[a-z]+\z/i')) {
            return 'alpha';
        }

        if (self::allMatch($values, '/\A[a-z0-9]+\z/i')) {
            return 'alnum';
        }

        if (self::allMatch($values, '/\A[a-z0-9][a-z0-9_-]*\z/i')) {
            return 'slug';
        }

        $emails = 0;
        foreach ($values as $value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                $emails++;
            }
        }

        if ($emails === count($values)) {
            return 'email';
        }

        return 'text';
    }

    protected static function inferAllowedChars(array $values): string
    {
        if (self::allMatch($values, '/\A[a-z]+\z/i')) {
            return 'alpha';
        }

        if (self::allMatch($values, '/\A[a-z0-9]+\z/i')) {
            return 'alnum';
        }

        if (self::allMatch($values, '/\A[a-z0-9_-]+\z/i')) {
            return 'slug';
        }

        return 'free_text';
    }

    protected static function dominantShape(array $values): ?string
    {
        $counts = [];
        foreach ($values as $value) {
            $shape = ShapeModel::shape($value);
            $counts[$shape] = ($counts[$shape] ?? 0) + 1;
        }

        arsort($counts);
        $shape = (string) key($counts);
        $count = (int) current($counts);

        return $count / count($values) >= 0.9 ? $shape : null;
    }

    protected static function percentile(array $sorted, float $percentile): int
    {
        $index = (int) ceil(count($sorted) * $percentile) - 1;
        $index = max(0, min(count($sorted) - 1, $index));

        return (int) $sorted[$index];
    }

    protected static function allMatch(array $values, string $regex): bool
    {
        foreach ($values as $value) {
            if ($value === '' || preg_match($regex, $value) !== 1) {
                return false;
            }
        }

        return true;
    }
}
