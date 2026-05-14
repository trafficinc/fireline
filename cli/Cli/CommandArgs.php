<?php

namespace Cli;

class CommandArgs
{
    public static function hasFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }

    public static function firstValue(array $argv, int $offset, string $default, array $valueOptions = []): string
    {
        $values = self::values($argv, $offset, $valueOptions);

        return $values[0] ?? $default;
    }

    public static function values(array $argv, int $offset, array $valueOptions = []): array
    {
        $values = [];
        $skipNext = false;
        $args = array_slice($argv, $offset);
        foreach ($args as $index => $arg) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            $arg = (string) $arg;
            if (strpos($arg, '--') === 0) {
                if (in_array($arg, $valueOptions, true) && isset($args[$index + 1]) && strpos((string) $args[$index + 1], '--') !== 0) {
                    $skipNext = true;
                }
                continue;
            }

            $values[] = $arg;
        }

        return $values;
    }

    public static function intValue(array $argv, int $offset, int $default, int $min = 1): int
    {
        $value = self::firstValue($argv, $offset, (string) $default);

        return max($min, (int) $value);
    }

    public static function optionValue(array $argv, string $option, ?string $default = null): ?string
    {
        foreach ($argv as $index => $arg) {
            $arg = (string) $arg;
            if ($arg === $option) {
                $next = $argv[$index + 1] ?? null;
                return $next !== null && strpos((string) $next, '--') !== 0 ? (string) $next : $default;
            }

            $prefix = $option . '=';
            if (strpos($arg, $prefix) === 0) {
                return substr($arg, strlen($prefix));
            }
        }

        return $default;
    }
}
