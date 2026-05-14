<?php

namespace Fireline\Heuristics;

class EncodingHeuristics
{
    public static function analyze(string $value): int
    {
        $score = 0;
        if (preg_match_all('/%[0-9a-f]{2}/i', $value, $matches) && count($matches[0]) > 3) {
            $score += 4;
        }

        if (strpos($value, '&#x') !== false || strpos($value, '\\x') !== false) {
            $score += 4;
        }

        return $score;
    }
}
