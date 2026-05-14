<?php

namespace Fireline\Heuristics;

class EntropyHeuristics
{
    public static function analyze(string $value): int
    {
        if (strlen($value) < 64) {
            return 0;
        }

        $unique = count(array_unique(str_split($value)));
        return $unique > 40 ? 3 : 0;
    }
}
