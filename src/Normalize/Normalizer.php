<?php

namespace Fireline\Normalize;

class Normalizer
{
    public function run(string $value): string
    {
        $value = UrlDecoder::repeatedDecode($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = CommentStripper::stripSqlComments($value);
        $value = UnicodeNormalizer::normalize($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim(is_string($value) ? $value : '');
    }
}
