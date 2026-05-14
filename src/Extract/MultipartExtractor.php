<?php

namespace Fireline\Extract;

class MultipartExtractor
{
    public static function extract(array $files): array
    {
        $fields = [];
        foreach ($files as $field => $file) {
            if (!is_array($file)) {
                continue;
            }

            $fields[(string) $field] = self::normalizeFile($file);
        }

        return $fields;
    }

    protected static function normalizeFile(array $file): array
    {
        if (isset($file['name']) && is_array($file['name'])) {
            $items = [];
            foreach ($file['name'] as $index => $name) {
                $items[(string) $index] = self::normalizeFile([
                    'name' => $name,
                    'type' => self::nestedValue($file['type'] ?? [], $index),
                    'size' => self::nestedValue($file['size'] ?? [], $index),
                    'error' => self::nestedValue($file['error'] ?? [], $index),
                ]);
            }

            return $items;
        }

        return [
            'name' => (string) ($file['name'] ?? ''),
            'type' => (string) ($file['type'] ?? ''),
            'size' => (string) ($file['size'] ?? ''),
            'error' => (string) ($file['error'] ?? ''),
        ];
    }

    protected static function nestedValue($value, $index)
    {
        if (is_array($value)) {
            return $value[$index] ?? '';
        }

        return $value;
    }
}
