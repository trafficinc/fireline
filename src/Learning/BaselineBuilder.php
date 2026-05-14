<?php

namespace Fireline\Learning;

class BaselineBuilder
{
    public static function build(array $fields): array
    {
        return ['fields' => array_keys($fields)];
    }
}
