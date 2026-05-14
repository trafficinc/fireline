<?php

namespace Fireline\Learning;

class RouteModelExporter
{
    public static function toPhp(array $models): string
    {
        return "<?php\n\nreturn " . self::arrayToPhp($models, 0) . ";\n";
    }

    protected static function arrayToPhp(array $array, int $depth): string
    {
        if ($array === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth);
        $childIndent = str_repeat('    ', $depth + 1);
        $lines = ['['];

        foreach ($array as $key => $value) {
            $line = $childIndent . self::keyToPhp($key) . ' => ';
            $line .= is_array($value)
                ? self::arrayToPhp($value, $depth + 1)
                : self::valueToPhp($value);
            $lines[] = $line . ',';
        }

        $lines[] = $indent . ']';

        return implode(PHP_EOL, $lines);
    }

    protected static function keyToPhp($key): string
    {
        return is_int($key) ? (string) $key : self::valueToPhp((string) $key);
    }

    protected static function valueToPhp($value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value) . "'";
    }
}
