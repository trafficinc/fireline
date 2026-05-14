<?php

namespace Fireline\Scoring;

class ScoreAccumulator
{
    protected $total = 0;
    protected $breakdown = [];

    public function add(string $source, int $score): void
    {
        if ($score <= 0) {
            return;
        }

        $this->total += $score;
        $this->breakdown[$source] = ($this->breakdown[$source] ?? 0) + $score;
    }

    public function addRule(array $rule): void
    {
        $this->add('rule:' . (string) ($rule['id'] ?? 'unknown'), (int) ($rule['score'] ?? 0));
    }

    public function total(): int
    {
        return $this->total;
    }

    public function breakdown(): array
    {
        return $this->breakdown;
    }
}
