<?php

namespace Fireline\Normalize;

class UnicodeNormalizer
{
    public static function normalize(string $value): string
    {
        if (class_exists('\Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_KC);
            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return $value;
    }
}
