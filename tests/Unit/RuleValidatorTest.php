<?php

use Fireline\Rules\RuleValidator;
use PHPUnit\Framework\TestCase;

class RuleValidatorTest extends TestCase
{
    public function testBundledRulesAreValid(): void
    {
        $result = (new RuleValidator())->validateFile(__DIR__ . '/../../config/rules.php');

        $this->assertTrue($result['ok'], json_encode($result['errors']));
        $this->assertGreaterThan(0, $result['total']);
        $this->assertSame([], $result['errors']);
    }

    public function testReportsInvalidRuleMetadata(): void
    {
        $result = (new RuleValidator())->validate([
            [
                'id' => 'DUPLICATE',
                'type' => 'keyword',
                'pattern' => 'select',
                'score' => 8,
                'category' => 'sqli',
                'paranoia' => 'low',
                'explanation' => 'test',
                'examples' => ['select 1'],
                'false_positives' => ['docs'],
            ],
            [
                'id' => 'DUPLICATE',
                'type' => 'regex',
                'pattern' => '/unterminated',
                'score' => 0,
                'category' => 'unknown',
                'paranoia' => 'maximum',
                'requires' => 'MISSING_RULE',
                'explanation' => '',
                'examples' => [],
                'false_positives' => 'docs',
            ],
        ]);

        $messages = array_column($result['errors'], 'message');

        $this->assertFalse($result['ok']);
        $this->assertContains('Rule id must be unique.', $messages);
        $this->assertContains('Regex pattern is invalid.', $messages);
        $this->assertContains('Rule score must be a positive integer.', $messages);
        $this->assertContains('Rule category is not supported: unknown', $messages);
        $this->assertContains('Rule paranoia must be low, medium, high, or strict.', $messages);
        $this->assertContains('Rule metadata must not be empty.', $messages);
        $this->assertContains('Rule metadata must be a non-empty array.', $messages);
        $this->assertContains('Regex rules must include benchmark metadata.', $messages);
        $this->assertContains('Regex rule requires metadata must be an array.', $messages);
    }

    public function testReportsMissingRegexRequirements(): void
    {
        $result = (new RuleValidator())->validate([
            [
                'id' => 'REGEX_RULE',
                'type' => 'regex',
                'pattern' => '/select/',
                'score' => 1,
                'category' => 'sqli',
                'paranoia' => 'low',
                'requires' => ['MISSING_RULE'],
                'benchmark' => 'Simple literal regex.',
                'explanation' => 'test',
                'examples' => ['select'],
                'false_positives' => ['docs'],
            ],
        ]);

        $messages = array_column($result['errors'], 'message');

        $this->assertFalse($result['ok']);
        $this->assertContains('Required rule does not exist: MISSING_RULE', $messages);
    }

    public function testReportsUnreadableRuleFile(): void
    {
        $path = sys_get_temp_dir() . '/fireline-missing-rules-' . uniqid('', true) . '.php';
        $result = (new RuleValidator())->validateFile($path);

        $this->assertFalse($result['ok']);
        $this->assertSame($path, $result['path']);
        $this->assertSame('Rule file is not readable.', $result['errors'][0]['message']);
    }
}
