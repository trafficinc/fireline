<?php

namespace Fireline\Heuristics;

class XssHeuristics
{
    public static function analyze(string $value): int
    {
        $score = 0;
        if (strpos($value, '<') !== false && strpos($value, '>') !== false) {
            $score += 6;
        }

        if (strpos($value, 'javascript:') !== false) {
            $score += 8;
        }

        if (preg_match('/\bon[a-z]{2,30}\s*=/i', $value)) {
            $score += 8;
        }

        return $score;
    }
}
