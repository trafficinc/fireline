<?php

namespace Fireline\Engine;

class RequestContext
{
    protected $request;
    protected $results = [];

    public function __construct(array $request)
    {
        $this->request = $request;
    }

    public function request(): array
    {
        return $this->request;
    }

    public function addResult(array $result): void
    {
        $this->results[] = $result;
    }

    public function results(): array
    {
        return $this->results;
    }

    public function totalScore(): int
    {
        $total = 0;
        foreach ($this->results as $result) {
            $total += (int) ($result['score'] ?? 0);
        }

        return $total;
    }

    public function highestScore(): int
    {
        $highest = 0;
        foreach ($this->results as $result) {
            $highest = max($highest, (int) ($result['score'] ?? 0));
        }

        return $highest;
    }
}
