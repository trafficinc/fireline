<?php

namespace Fireline\Scan;

use Fireline\Rules\Rule;
use Fireline\Rules\RuleLoader;
use Fireline\Telemetry\RuleMetrics;

class AhoCorasick
{
    protected static $tries = [];

    public static function boot(string $paranoia = 'strict'): Trie
    {
        $trie = new Trie();
        foreach (RuleLoader::keywordRulesFor($paranoia) as $rule) {
            /** @var Rule $rule */
            $trie->add($rule->pattern(), $rule->toArray());
        }

        return $trie;
    }

    public static function scan(string $input, string $paranoia = 'strict'): array
    {
        $started = microtime(true);
        $paranoia = self::normalizeParanoia($paranoia);
        if (!isset(self::$tries[$paranoia])) {
            self::$tries[$paranoia] = self::boot($paranoia);
        }

        $matches = self::$tries[$paranoia]->search($input);
        RuleMetrics::timing('scanner.aho_corasick', (microtime(true) - $started) * 1000);

        foreach ($matches as $match) {
            RuleMetrics::increment('rule.' . (string) ($match['id'] ?? 'unknown') . '.matched');
        }

        return $matches;
    }

    public static function reset(): void
    {
        self::$tries = [];
    }

    protected static function normalizeParanoia(string $paranoia): string
    {
        $paranoia = strtolower($paranoia);

        return in_array($paranoia, ['low', 'medium', 'high', 'strict'], true) ? $paranoia : 'medium';
    }
}
