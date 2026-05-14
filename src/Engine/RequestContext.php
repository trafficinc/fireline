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

    public function addResult($result): void
    {
        if ($result instanceof ScanResult) {
            $this->results[] = $result->toArray();
            return;
        }

        $this->results[] = ScanResult::fromArray(is_array($result) ? $result : [])->toArray();
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
