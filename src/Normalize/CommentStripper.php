<?php

namespace Fireline\Normalize;

class CommentStripper
{
    public static function stripSqlComments(string $value): string
    {
        $value = preg_replace('/\/\*.{0,512}?\*\//s', ' ', $value);
        $value = preg_replace('/--[^\r\n]*/', ' ', $value);
        $value = preg_replace('/#[^\r\n]*/', ' ', $value);

        return is_string($value) ? $value : '';
    }
}
