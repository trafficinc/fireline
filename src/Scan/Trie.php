<?php

namespace Fireline\Scan;

class Trie
{
    protected $root = [
        'children' => [],
        'outputs' => [],
    ];

    public function add(string $term, array $payload = []): void
    {
        $term = strtolower($term);
        if ($term === '') {
            return;
        }

        $node =& $this->root;
        $length = strlen($term);
        for ($i = 0; $i < $length; $i++) {
            $char = $term[$i];
            if (!isset($node['children'][$char])) {
                $node['children'][$char] = [
                    'children' => [],
                    'outputs' => [],
                ];
            }

            $node =& $node['children'][$char];
        }

        $node['outputs'][] = $payload + ['pattern' => $term];
    }

    public function search(string $input): array
    {
        $input = strtolower($input);
        $matches = [];
        $seen = [];
        $length = strlen($input);

        for ($start = 0; $start < $length; $start++) {
            $node = $this->root;
            for ($i = $start; $i < $length; $i++) {
                $char = $input[$i];
                if (!isset($node['children'][$char])) {
                    break;
                }

                $node = $node['children'][$char];
                foreach ($node['outputs'] as $output) {
                    $key = (string) ($output['id'] ?? $output['pattern']);
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $matches[] = $output;
                }
            }
        }

        return $matches;
    }
}
