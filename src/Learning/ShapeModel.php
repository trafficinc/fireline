<?php

namespace Fireline\Learning;

class ShapeModel
{
    public static function shape(string $value): string
    {
        $value = preg_replace('/[a-z]+/i', 'A', $value);
        $value = preg_replace('/\d+/', 'N', is_string($value) ? $value : '');

        return is_string($value) ? $value : '';
    }
}
