<?php

namespace Fireline\Scoring;

class Confidence
{
    public static function fromScore(int $score): string
    {
        if ($score >= 25) {
            return 'high';
        }

        if ($score >= 10) {
            return 'medium';
        }

        return 'low';
    }
}
