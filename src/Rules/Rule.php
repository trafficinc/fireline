<?php

namespace Fireline\Rules;

class Rule
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function id(): string
    {
        return (string) ($this->data['id'] ?? '');
    }

    public function type(): string
    {
        return (string) ($this->data['type'] ?? '');
    }

    public function pattern(): string
    {
        return (string) ($this->data['pattern'] ?? '');
    }

    public function score(): int
    {
        return (int) ($this->data['score'] ?? 0);
    }

    public function category(): string
    {
        return (string) ($this->data['category'] ?? '');
    }

    public function requires(): array
    {
        return is_array($this->data['requires'] ?? null) ? $this->data['requires'] : [];
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
