<?php

abstract class BaseFilter
{
    /**
     * @var array
     */
    protected $compares = [];

    /**
     * @var string
     */
    protected $compares_file = "";

    /**
     * BaseFilter constructor.
     */
    public function __construct(){
        $compares = require __DIR__ . '/Compares/' . $this->compares_file;

        if ($compares !== false) {
            $this->compares = $compares;
        }

    }

    protected function ruleMatches(string $rule, string $value): bool {
        $matched = @preg_match('/'.$rule.'/i', $value, $matches);

        if ($matched === false) {
            return true;
        }

        if (function_exists('preg_last_error') && preg_last_error() !== PREG_NO_ERROR) {
            return true;
        }

        return !empty($matches);
    }

    /**
     * Check given string
     *
     * @param string $value
     * @param array $configs
     * @return bool
     */
    abstract public function safe(string $value, array $configs): bool;
}
