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

    protected function shouldSkipRule(string $rule): bool {
        return empty($rule) || strpos($rule, '#') === 0;
    }

    protected function unsafeRuleFor(string $value): ?string {
        foreach ($this->compares as $compared) {
            $compared = trim($compared);
            if ($this->shouldSkipRule($compared)) {
                continue;
            }

            if ($this->ruleMatches($compared, $value)) {
                return $compared;
            }
        }

        return null;
    }

    protected function unsafeEngineRuleFor(string $value, array $configs, array $categories): ?string
    {
        $engineConfig = array_merge([
            'paranoia_level' => 'strict',
            'regex_threshold' => 1,
            'score_threshold' => 12,
        ], $configs);

        $normalizer = new \Fireline\Normalize\Normalizer();
        $thresholds = new \Fireline\Scoring\Thresholds($engineConfig);
        $inspector = new \Fireline\Engine\FieldInspector($thresholds);
        $field = new \Fireline\Extract\RequestField('legacy.value', $value, 'legacy');
        $request = [
            'method' => (string) ($engineConfig['request_method'] ?? 'GET'),
            'route' => (string) ($engineConfig['route'] ?? ''),
        ];

        $result = $inspector->inspect($request, $field, $normalizer->run($value), false);
        if ($result === null || $result->score() < $thresholds->blockThreshold()) {
            return null;
        }

        $allowed = array_flip(array_map('strtolower', $categories));
        foreach ($result->toArray()['matches'] as $match) {
            $category = strtolower((string) ($match['category'] ?? ''));
            if (isset($allowed[$category])) {
                return (string) ($match['id'] ?? $category);
            }
        }

        return null;
    }

    /**
     * Check given string
     *
     * @param string $value
     * @param array $configs
     * @return bool
     */
    public function safe(string $value, array $configs): bool
    {
        return $this->unsafeRuleFor($value) === null;
    }
}
