<?php

namespace Fireline\Scan;

use Fireline\Rules\Rule;
use Fireline\Rules\RuleLoader;
use Fireline\Telemetry\RuleMetrics;

class AhoCorasick
{
    protected static $trie;

    public static function boot(): Trie
    {
        $trie = new Trie();
        foreach (RuleLoader::keywordRules() as $rule) {
            /** @var Rule $rule */
            $trie->add($rule->pattern(), $rule->toArray());
        }

        return $trie;
    }

    public static function scan(string $input): array
    {
        $started = microtime(true);
        if (self::$trie === null) {
            self::$trie = self::boot();
        }

        $matches = self::$trie->search($input);
        RuleMetrics::timing('scanner.aho_corasick', (microtime(true) - $started) * 1000);

        foreach ($matches as $match) {
            RuleMetrics::increment('rule.' . (string) ($match['id'] ?? 'unknown') . '.matched');
        }

        return $matches;
    }

    public static function reset(): void
    {
        self::$trie = null;
    }
}
