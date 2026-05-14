<?php

namespace Fireline\Heuristics;

class SqlHeuristics
{
    public static function analyze(string $value): int
    {
        $score = 0;
        $tokens = [' union ', ' select ', ' from ', ' where ', ' or ', ' and ', 'sleep(', 'benchmark('];
        foreach ($tokens as $token) {
            if (strpos(' ' . $value . ' ', $token) !== false) {
                $score += 3;
            }
        }

        if (substr_count($value, '=') > 2) {
            $score += 2;
        }

        return $score;
    }
}
