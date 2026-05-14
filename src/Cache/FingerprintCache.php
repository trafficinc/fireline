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
        $signals = self::signals($value);
        $value = preg_replace('/\d+/', 'N', $value);
        $value = preg_replace('/[a-z]+/i', 'A', is_string($value) ? $value : '');

        return (is_string($value) ? $value : '') . '|sig:' . implode(',', $signals);
    }

    protected static function signals(string $value): array
    {
        $checks = [
            'sql_union' => '/\bunion\b/i',
            'sql_select' => '/\bselect\b/i',
            'sql_boolean' => '/\b(?:or|and)\b\s+\d+\s*=\s*\d+/i',
            'sql_sleep' => '/\bsleep\s*\(/i',
            'xss_script' => '/<\s*script\b/i',
            'xss_js_uri' => '/javascript\s*:/i',
            'xss_event' => '/\bon[a-z]+\s*=/i',
            'shell_bin' => '#/(?:bin|usr/bin)/(?:sh|bash|zsh|dash|nc|curl|wget)\b#i',
            'path_traversal' => '#(?:\.\./|\.\.\\\\)#',
            'php_upload' => '/\.(?:php[0-9]?|phtml|phar)\b/i',
        ];

        $signals = [];
        foreach ($checks as $name => $pattern) {
            if (preg_match($pattern, $value) === 1) {
                $signals[] = $name;
            }
        }

        return $signals;
    }
}
