<?php

namespace Fireline\Scan;

use Fireline\Rules\Rule;
use Fireline\Rules\RuleLoader;
use Fireline\Telemetry\RuleMetrics;

class RegexScanner
{
    public static function scan(string $input, array $matches, string $paranoia = 'strict'): int
    {
        return self::scanDetailed($input, $matches, $paranoia)['score'];
    }

    public static function scanDetailed(string $input, array $matches, string $paranoia = 'strict'): array
    {
        $score = 0;
        $regexMatches = [];
        $matchedRuleKeys = [];
        foreach ($matches as $match) {
            $matchedRuleKeys[] = (string) ($match['id'] ?? '');
            $matchedRuleKeys[] = (string) ($match['pattern'] ?? '');
        }

        foreach (RuleLoader::regexRulesFor($paranoia) as $rule) {
            /** @var Rule $rule */
            if (!self::requirementsMet($rule, $matchedRuleKeys)) {
                continue;
            }

            RuleMetrics::increment('rule.' . $rule->id() . '.executed');
            $started = microtime(true);
            $matched = @preg_match($rule->pattern(), $input);
            RuleMetrics::timing('rule.' . $rule->id(), (microtime(true) - $started) * 1000);

            if ($matched === 1 && preg_last_error() === PREG_NO_ERROR) {
                RuleMetrics::increment('rule.' . $rule->id() . '.matched');
                $score += $rule->score();
                $regexMatches[] = $rule->toArray();
            }
        }

        return [
            'score' => $score,
            'matches' => $regexMatches,
        ];
    }

    protected static function requirementsMet(Rule $rule, array $keywords): bool
    {
        $lookup = array_fill_keys($keywords, true);
        foreach ($rule->requires() as $required) {
            if (!isset($lookup[(string) $required])) {
                return false;
            }
        }

        return true;
    }
}
