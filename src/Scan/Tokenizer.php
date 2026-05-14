<?php

namespace Fireline\Scan;

class Tokenizer
{
    public static function tokens(string $input): array
    {
        $tokens = preg_split('/[^a-z0-9_]+/i', $input, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($tokens) ? $tokens : [];
    }
}
