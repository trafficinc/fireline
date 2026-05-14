<?php

namespace Fireline\Cache;

use Fireline\Extract\RequestField;

class FingerprintCache
{
    public static function build(array $request, RequestField $field, string $normalized): string
    {
        return sha1(
            ($request['method'] ?? '') .
            ($request['route'] ?? '') .
            $field->name() .
            self::shape($normalized)
        );
    }

    protected static function shape(string $value): string
    {
        $value = preg_replace('/\d+/', 'N', $value);
        $value = preg_replace('/[a-z]+/i', 'A', is_string($value) ? $value : '');

        return is_string($value) ? $value : '';
    }
}
