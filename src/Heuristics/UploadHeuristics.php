<?php

namespace Fireline\Heuristics;

use Fireline\Extract\RequestField;

class UploadHeuristics
{
    protected static $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'asp', 'aspx', 'jsp', 'jspx',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash',
        'exe', 'dll', 'com', 'bat', 'cmd', 'ps1',
    ];

    protected static $dangerousMimeFragments = [
        'php',
        'x-httpd',
        'x-msdownload',
        'x-sh',
        'shellscript',
        'executable',
    ];

    public static function analyze(RequestField $field, string $normalized): int
    {
        if ($field->source() !== 'file') {
            return 0;
        }

        $name = strtolower($field->name());
        if (substr($name, -5) === '.name') {
            return self::filenameScore($normalized);
        }

        if (substr($name, -5) === '.type') {
            return self::mimeScore($normalized);
        }

        return 0;
    }

    protected static function filenameScore(string $value): int
    {
        $score = 0;
        $filename = strtolower(basename(str_replace('\\', '/', $value)));

        if (strpos($filename, "\0") !== false || strpos($filename, '%00') !== false) {
            $score += 12;
        }

        $parts = array_values(array_filter(explode('.', $filename), 'strlen'));
        $extension = count($parts) > 1 ? end($parts) : '';
        if (in_array($extension, self::$dangerousExtensions, true)) {
            $score += 22;
        }

        if (count($parts) > 2 && in_array($extension, self::$dangerousExtensions, true)) {
            $score += 5;
        }

        if (preg_match('/\.(php|phtml|phar)\d?$/i', $filename)) {
            $score += 4;
        }

        return min(30, $score);
    }

    protected static function mimeScore(string $value): int
    {
        $mime = strtolower($value);
        foreach (self::$dangerousMimeFragments as $fragment) {
            if (strpos($mime, $fragment) !== false) {
                return 18;
            }
        }

        return 0;
    }
}
