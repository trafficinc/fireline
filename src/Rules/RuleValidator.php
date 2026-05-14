<?php

namespace Fireline\Rules;

class RuleValidator
{
    private const REQUIRED_FIELDS = [
        'id',
        'type',
        'pattern',
        'score',
        'category',
        'paranoia',
        'explanation',
        'examples',
        'false_positives',
    ];

    private const TYPES = ['keyword', 'regex'];
    private const PARANOIA_LEVELS = ['low', 'medium', 'high', 'strict'];
    private const CATEGORIES = [
        Categories::SQLI,
        Categories::XSS,
        Categories::RCE,
        Categories::SHELL,
        Categories::LFI,
        Categories::RFI,
        Categories::WEB_SHELL,
        Categories::SCANNER,
        Categories::ENCODING,
        Categories::PROTOCOL,
        Categories::PHP_INJECTION,
        Categories::UPLOAD,
    ];

    public function validateFile(string $path): array
    {
        if (!is_readable($path)) {
            return [
                'ok' => false,
                'path' => $path,
                'total' => 0,
                'errors' => [['rule' => '', 'field' => 'path', 'message' => 'Rule file is not readable.']],
            ];
        }

        $rules = require $path;
        if (!is_array($rules)) {
            return [
                'ok' => false,
                'path' => $path,
                'total' => 0,
                'errors' => [['rule' => '', 'field' => 'file', 'message' => 'Rule file must return an array.']],
            ];
        }

        $result = $this->validate($rules);
        $result['path'] = $path;

        return $result;
    }

    public function validate(array $rules): array
    {
        $errors = [];
        $ids = [];
        $references = [];

        foreach ($rules as $index => $rule) {
            if (!is_array($rule)) {
                $errors[] = $this->error((string) $index, 'rule', 'Rule must be an array.');
                continue;
            }

            $id = (string) ($rule['id'] ?? '#' . $index);
            foreach (self::REQUIRED_FIELDS as $field) {
                if (!array_key_exists($field, $rule)) {
                    $errors[] = $this->error($id, $field, 'Required rule metadata is missing.');
                }
            }

            if ($id === '') {
                $errors[] = $this->error('#' . $index, 'id', 'Rule id must not be empty.');
            } elseif (isset($ids[$id])) {
                $errors[] = $this->error($id, 'id', 'Rule id must be unique.');
            }
            $ids[$id] = true;

            $type = strtolower((string) ($rule['type'] ?? ''));
            if (!in_array($type, self::TYPES, true)) {
                $errors[] = $this->error($id, 'type', 'Rule type must be keyword or regex.');
            }

            $pattern = (string) ($rule['pattern'] ?? '');
            if ($pattern === '') {
                $errors[] = $this->error($id, 'pattern', 'Rule pattern must not be empty.');
            } elseif ($type === 'regex' && @preg_match($pattern, '') === false) {
                $errors[] = $this->error($id, 'pattern', 'Regex pattern is invalid.');
            }

            if ((int) ($rule['score'] ?? 0) <= 0) {
                $errors[] = $this->error($id, 'score', 'Rule score must be a positive integer.');
            }

            $category = strtolower((string) ($rule['category'] ?? ''));
            if (!in_array($category, self::CATEGORIES, true)) {
                $errors[] = $this->error($id, 'category', 'Rule category is not supported: ' . $category);
            }

            $paranoia = strtolower((string) ($rule['paranoia'] ?? ''));
            if (!in_array($paranoia, self::PARANOIA_LEVELS, true)) {
                $errors[] = $this->error($id, 'paranoia', 'Rule paranoia must be low, medium, high, or strict.');
            }

            foreach (['explanation'] as $field) {
                if (array_key_exists($field, $rule) && trim((string) $rule[$field]) === '') {
                    $errors[] = $this->error($id, $field, 'Rule metadata must not be empty.');
                }
            }

            foreach (['examples', 'false_positives'] as $field) {
                if (array_key_exists($field, $rule) && (!is_array($rule[$field]) || $rule[$field] === [])) {
                    $errors[] = $this->error($id, $field, 'Rule metadata must be a non-empty array.');
                }
            }

            if ($type === 'regex') {
                foreach (['requires', 'benchmark'] as $field) {
                    if (!array_key_exists($field, $rule)) {
                        $errors[] = $this->error($id, $field, 'Regex rules must include ' . $field . ' metadata.');
                    }
                }

                if (array_key_exists('requires', $rule) && !is_array($rule['requires'])) {
                    $errors[] = $this->error($id, 'requires', 'Regex rule requires metadata must be an array.');
                }

                if (array_key_exists('benchmark', $rule) && trim((string) $rule['benchmark']) === '') {
                    $errors[] = $this->error($id, 'benchmark', 'Regex rule benchmark metadata must not be empty.');
                }

                foreach ((array) ($rule['requires'] ?? []) as $required) {
                    $references[] = ['rule' => $id, 'requires' => (string) $required];
                }
            }
        }

        foreach ($references as $reference) {
            if (!isset($ids[$reference['requires']])) {
                $errors[] = $this->error($reference['rule'], 'requires', 'Required rule does not exist: ' . $reference['requires']);
            }
        }

        return [
            'ok' => $errors === [],
            'total' => count($rules),
            'errors' => $errors,
        ];
    }

    private function error(string $rule, string $field, string $message): array
    {
        return [
            'rule' => $rule,
            'field' => $field,
            'message' => $message,
        ];
    }
}
