<?php

namespace Fireline\Engine;

class Decision
{
    protected $blocked;
    protected $score;
    protected $reason;
    protected $results;
    protected $context;
    protected $matchedResult;

    public function __construct(bool $blocked, int $score = 0, string $reason = '', array $results = [], ?RequestContext $context = null, array $matchedResult = [])
    {
        $this->blocked = $blocked;
        $this->score = $score;
        $this->reason = $reason;
        $this->results = $results;
        $this->context = $context;
        $this->matchedResult = $matchedResult;
    }

    public static function allow(RequestContext $context): self
    {
        return new self(false, $context->totalScore(), 'allowed', $context->results(), $context);
    }

    public static function block(RequestContext $context, string $reason, array $matchedResult = []): self
    {
        if (empty($matchedResult)) {
            $results = $context->results();
            $last = end($results);
            $matchedResult = is_array($last) ? $last : [];
        }

        return new self(true, $context->totalScore(), $reason, $context->results(), $context, $matchedResult);
    }

    public function shouldBlock(): bool
    {
        return $this->blocked;
    }

    public function blocked(): bool
    {
        return $this->shouldBlock();
    }

    public function score(): int
    {
        return $this->score;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function results(): array
    {
        return $this->results;
    }

    public function matchedResult(): array
    {
        return $this->matchedResult;
    }

    public function context()
    {
        return $this->context;
    }
}
