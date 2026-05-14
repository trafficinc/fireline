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

    public function __construct(bool $blocked, int $score = 0, string $reason = '', array $results = [], ?RequestContext $context = null, $matchedResult = [])
    {
        $this->blocked = $blocked;
        $this->score = $score;
        $this->reason = $reason;
        $this->results = $results;
        $this->context = $context;
        $this->matchedResult = $matchedResult instanceof ScanResult ? $matchedResult->toArray() : $matchedResult;
    }

    public static function allow(RequestContext $context): self
    {
        return new self(false, $context->totalScore(), 'allowed', $context->results(), $context);
    }

    public static function block(RequestContext $context, string $reason, $matchedResult = []): self
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

    public function explanation(int $threshold = 0): array
    {
        $result = $this->matchedResult ?: $this->highestResult();
        $breakdown = is_array($result['breakdown'] ?? null) ? $result['breakdown'] : [];
        arsort($breakdown);

        return [
            'decision' => $this->blocked ? 'blocked' : 'allowed',
            'reason' => $this->reason,
            'field' => (string) ($result['field'] ?? ''),
            'score' => (int) ($result['score'] ?? $this->score),
            'threshold' => $threshold,
            'signals' => $breakdown,
            'matched_rules' => array_values(array_map(function (array $match): string {
                return (string) ($match['id'] ?? $match['pattern'] ?? 'unknown');
            }, is_array($result['matches'] ?? null) ? $result['matches'] : [])),
        ];
    }

    public function explain(int $threshold = 0): string
    {
        $explanation = $this->explanation($threshold);
        $lines = [
            ucfirst($explanation['decision']) . ':',
        ];

        foreach ($explanation['signals'] as $name => $score) {
            $lines[] = '- ' . $name . ' (+' . $score . ')';
        }

        $lines[] = 'Final Score: ' . $explanation['score'];
        if ($threshold > 0) {
            $lines[] = 'Threshold: ' . $threshold;
        }

        return implode(PHP_EOL, $lines);
    }

    public function context()
    {
        return $this->context;
    }

    protected function highestResult(): array
    {
        $highest = [];
        foreach ($this->results as $result) {
            if (!is_array($result)) {
                continue;
            }

            if ((int) ($result['score'] ?? 0) > (int) ($highest['score'] ?? -1)) {
                $highest = $result;
            }
        }

        return $highest;
    }
}
