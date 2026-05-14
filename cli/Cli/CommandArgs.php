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
        foreach (array_slice($argv, $offset) as $arg) {
            if (strpos($arg, '--') !== 0) {
                return $arg;
            }
        }

        return $default;
    }

    public static function intValue(array $argv, int $offset, int $default, int $min = 1): int
    {
        $value = self::firstValue($argv, $offset, (string) $default);

        return max($min, (int) $value);
    }
}
