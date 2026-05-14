<?php

namespace Fireline\Heuristics;

class EntropyHeuristics
{
    public static function analyze(string $value): int
    {
        $length = strlen($value);
        if ($length < 64) {
            return 0;
        }

        $entropy = self::shannonEntropy($value);
        $score = 0;

        if ($entropy >= 5.2) {
            $score += 3;
        }

        if ($length >= 256 && $entropy >= 4.8) {
            $score += 2;
        }

        if (self::looksEncoded($value) && $entropy >= 4.5) {
            $score += 3;
        }

        return min(8, $score);
    }

    public static function shannonEntropy(string $value): float
    {
        $length = strlen($value);
        if ($length === 0) {
            return 0.0;
        }

        $counts = count_chars($value, 1);
        $entropy = 0.0;
        foreach ($counts as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    protected static function looksEncoded(string $value): bool
    {
        if (preg_match('/\A[a-z0-9+\/=_-]{64,}\z/i', $value) !== 1) {
            return false;
        }

        return preg_match('/[A-Z]/', $value) === 1
            && preg_match('/[a-z]/', $value) === 1
            && preg_match('/\d/', $value) === 1;
    }
}
