<?php

namespace Fireline\Extract;

class RequestField
{
    protected $name;
    protected $value;
    protected $source;

    public function __construct(string $name, string $value, string $source)
    {
        $this->name = $name;
        $this->value = $value;
        $this->source = $source;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function source(): string
    {
        return $this->source;
    }
}
