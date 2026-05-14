<?php

namespace Fireline\Heuristics;

class ShellHeuristics
{
    public static function analyze(string $value): int
    {
        $score = 0;
        foreach (['/bin/sh', '/bin/bash', 'cmd.exe', 'powershell', 'wget ', 'curl '] as $token) {
            if (strpos($value, $token) !== false) {
                $score += 8;
            }
        }

        return $score;
    }
}
