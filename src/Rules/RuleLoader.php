<?php

namespace Fireline\Rules;

class RuleLoader
{
    protected static $rules;

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
}
