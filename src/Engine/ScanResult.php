<?php

namespace Fireline\Engine;

class ScanResult
{
    protected $field;
    protected $source;
    protected $score;
    protected $matches;
    protected $breakdown;
    protected $fingerprint;
    protected $value;
    protected $normalized;

    public function __construct(
        string $field,
        string $source,
        int $score,
        array $matches,
        array $breakdown,
        string $fingerprint,
        string $value,
        string $normalized
    ) {
        $this->field = $field;
        $this->source = $source;
        $this->score = $score;
        $this->matches = $matches;
        $this->breakdown = $breakdown;
        $this->fingerprint = $fingerprint;
        $this->value = $value;
        $this->normalized = $normalized;
    }

    public static function fromArray(array $result): self
    {
        return new self(
            (string) ($result['field'] ?? ''),
            (string) ($result['source'] ?? ''),
            (int) ($result['score'] ?? 0),
            is_array($result['matches'] ?? null) ? $result['matches'] : [],
            is_array($result['breakdown'] ?? null) ? $result['breakdown'] : [],
            (string) ($result['fingerprint'] ?? ''),
            (string) ($result['value'] ?? ''),
            (string) ($result['normalized'] ?? '')
        );
    }

    public function score(): int
    {
        return $this->score;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'source' => $this->source,
            'score' => $this->score,
            'matches' => $this->matches,
            'breakdown' => $this->breakdown,
            'fingerprint' => $this->fingerprint,
            'value' => $this->value,
            'normalized' => $this->normalized,
        ];
    }
}
