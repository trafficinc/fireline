<?php

namespace Fireline\Rules;

class RuleGroup
{
    protected $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function all(): array
    {
        return $this->rules;
    }

    public function byType(string $type): array
    {
        return array_values(array_filter($this->rules, function (Rule $rule) use ($type) {
            return $rule->type() === $type;
        }));
    }
}
