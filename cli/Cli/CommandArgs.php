<?php

namespace Cli;

class CommandArgs
{
    public static function hasFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }

    public static function firstValue(array $argv, int $offset, string $default): string
    {
        $values = self::values($argv, $offset);

        return $values[0] ?? $default;
    }

    public static function values(array $argv, int $offset): array
    {
        return array_values(array_filter(array_slice($argv, $offset), function ($arg): bool {
            return strpos((string) $arg, '--') !== 0;
        }));
    }

    public static function intValue(array $argv, int $offset, int $default, int $min = 1): int
    {
        $value = self::firstValue($argv, $offset, (string) $default);

        return max($min, (int) $value);
    }
}
