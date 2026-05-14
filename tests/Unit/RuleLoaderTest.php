<?php

use Fireline\Rules\RuleLoader;
use PHPUnit\Framework\TestCase;

class RuleLoaderTest extends TestCase
{
    public function testAllRulesHaveRequiredMetadata(): void
    {
        foreach (RuleLoader::all() as $rule) {
            $data = $rule->toArray();

            $this->assertNotSame('', $rule->id(), 'Rule id is required.');
            $this->assertContains($rule->type(), ['keyword', 'regex'], $rule->id() . ' type must be keyword or regex.');
            $this->assertNotSame('', $rule->pattern(), $rule->id() . ' pattern is required.');
            $this->assertGreaterThan(0, $rule->score(), $rule->id() . ' score must be positive.');
            $this->assertNotSame('', $rule->category(), $rule->id() . ' category is required.');
            $this->assertContains($rule->paranoia(), ['low', 'medium', 'high', 'strict'], $rule->id() . ' paranoia level is required.');
            $this->assertArrayHasKey('paranoia', $data, $rule->id() . ' paranoia metadata is required.');
            $this->assertArrayHasKey('explanation', $data, $rule->id() . ' explanation is required.');
            $this->assertArrayHasKey('examples', $data, $rule->id() . ' examples are required.');
            $this->assertArrayHasKey('false_positives', $data, $rule->id() . ' false positive notes are required.');
        }
    }

    public function testFiltersRulesByParanoiaLevel(): void
    {
        $lowIds = array_map(function ($rule) {
            return $rule->id();
        }, RuleLoader::forParanoia('low'));

        $strictIds = array_map(function ($rule) {
            return $rule->id();
        }, RuleLoader::forParanoia('strict'));

        $this->assertContains('SQL_UNION_SELECT', $lowIds);
        $this->assertNotContains('SQL_BOOLEAN_OR', $lowIds);
        $this->assertContains('SQL_BOOLEAN_OR', $strictIds);
        $this->assertGreaterThan(count($lowIds), count($strictIds));
    }

    public function testRegexRulesHavePreconditionsAndBenchmarkNotes(): void
    {
        foreach (RuleLoader::regexRules() as $rule) {
            $data = $rule->toArray();

            $this->assertArrayHasKey('requires', $data, $rule->id() . ' regex requires an explicit precondition list.');
            $this->assertArrayHasKey('benchmark', $data, $rule->id() . ' benchmark notes are required.');
            $this->assertSame(1, @preg_match($rule->pattern(), $this->exampleFor($rule->toArray())), $rule->id() . ' regex should match its example.');
            $this->assertSame(PREG_NO_ERROR, preg_last_error(), $rule->id() . ' regex must not trigger preg errors.');
        }
    }

    protected function exampleFor(array $rule): string
    {
        $examples = $rule['examples'] ?? [''];

        return (string) reset($examples);
    }
}
