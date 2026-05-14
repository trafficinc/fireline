<?php

namespace Fireline\Rules;

class RuleLoader
{
    protected static $rules;
    protected static $order = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'strict' => 4,
    ];

    public static function all(): array
    {
        if (self::$rules !== null) {
            return self::$rules;
        }

        $file = dirname(__DIR__, 2) . '/config/rules.php';
        $rules = is_readable($file) ? require $file : [];
        self::$rules = array_map(function (array $rule) {
            return new Rule($rule);
        }, is_array($rules) ? $rules : []);

        return self::$rules;
    }

    public static function keywordRules(): array
    {
        return (new RuleGroup(self::all()))->byType('keyword');
    }

    public static function regexRules(): array
    {
        return (new RuleGroup(self::all()))->byType('regex');
    }

    public static function keywordRulesFor(string $paranoia): array
    {
        return (new RuleGroup(self::forParanoia($paranoia)))->byType('keyword');
    }

    public static function regexRulesFor(string $paranoia): array
    {
        return (new RuleGroup(self::forParanoia($paranoia)))->byType('regex');
    }

    public static function metadataFor(string $paranoia): array
    {
        $rules = self::forParanoia($paranoia);
        $fingerprintSource = array_map(function (Rule $rule): array {
            $data = $rule->toArray();

            return [
                'id' => (string) ($data['id'] ?? ''),
                'type' => (string) ($data['type'] ?? ''),
                'pattern' => (string) ($data['pattern'] ?? ''),
                'score' => (int) ($data['score'] ?? 0),
                'category' => (string) ($data['category'] ?? ''),
                'paranoia' => $rule->paranoia(),
                'requires' => $rule->requires(),
            ];
        }, $rules);

        return [
            'count' => count($rules),
            'fingerprint' => sha1(json_encode($fingerprintSource)),
        ];
    }

    public static function forParanoia(string $paranoia): array
    {
        $max = self::$order[self::normalizeParanoia($paranoia)];

        return array_values(array_filter(self::all(), function (Rule $rule) use ($max) {
            return self::$order[$rule->paranoia()] <= $max;
        }));
    }

    protected static function normalizeParanoia(string $paranoia): string
    {
        $paranoia = strtolower($paranoia);

        return isset(self::$order[$paranoia]) ? $paranoia : 'medium';
    }
}
