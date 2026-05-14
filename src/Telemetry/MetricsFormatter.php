<?php

namespace Fireline\Telemetry;

class MetricsFormatter
{
    public static function text(array $snapshot): string
    {
        $lines = ['Metrics snapshot'];

        $sections = [
            'Counters' => $snapshot['counters'] ?? [],
            'Cache hit ratios' => $snapshot['cache_hit_ratios'] ?? [],
            'Timings' => $snapshot['timings'] ?? [],
            'Slowest rules' => $snapshot['slowest_rules'] ?? [],
        ];

        foreach ($sections as $title => $values) {
            $lines[] = '';
            $lines[] = $title . ':';

            if (!is_array($values) || $values === []) {
                $lines[] = '- none';
                continue;
            }

            foreach ($values as $name => $value) {
                $lines[] = '- ' . $name . ': ' . self::value($value);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    public static function json(array $snapshot): string
    {
        $encoded = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
    }

    protected static function value($value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = $key . '=' . self::scalar($item);
            }

            return implode(', ', $parts);
        }

        return self::scalar($value);
    }

    protected static function scalar($value): string
    {
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
