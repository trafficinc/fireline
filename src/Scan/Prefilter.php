<?php

namespace Fireline\Scan;

class Prefilter
{
    public static function analyze(string $value): int
    {
        $score = 0;

        if (strlen($value) > 4096) {
            $score += 3;
        }

        if (substr_count($value, "'") > 5) {
            $score += 3;
        }

        if (substr_count($value, '--') > 0) {
            $score += 4;
        }

        if (preg_match('/%[0-9a-f]{2}/i', $value)) {
            $score += 2;
        }

        if (strpos($value, "\0") !== false) {
            $score += 8;
        }

        return $score;
    }
}
