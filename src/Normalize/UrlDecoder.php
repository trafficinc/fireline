<?php

namespace Fireline\Normalize;

class UrlDecoder
{
    public static function repeatedDecode(string $value, int $limit = 3): string
    {
        for ($i = 0; $i < $limit; $i++) {
            $decoded = rawurldecode($value);
            if ($decoded === $value) {
                break;
            }

            $value = $decoded;
        }

        return $value;
    }
}
