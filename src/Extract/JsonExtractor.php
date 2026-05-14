<?php

namespace Fireline\Extract;

class JsonExtractor
{
    public static function extract(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return ['raw_json' => $body];
        }

        return $decoded;
    }
}
