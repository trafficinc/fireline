<?php

use Fireline\Extract\RequestField;
use Fireline\Learning\RouteLearner;
use Fireline\Telemetry\RuleMetrics;
use PHPUnit\Framework\TestCase;

class RouteLearnerTest extends TestCase
{
    protected function setUp(): void
    {
        RouteLearner::useModels([
            '/login' => [
                'post.username' => [
                    'type' => 'alnum',
                    'max_length' => 64,
                ],
                'post.password' => [
                    'type' => 'opaque',
                    'max_length' => 256,
                ],
            ],
        ]);
        RuleMetrics::reset();
    }

    protected function tearDown(): void
    {
        RouteLearner::reset();
    }

    public function testScoresUnexpectedShapeForKnownRouteField(): void
    {
        $field = new RequestField('post.username', 'union select', 'post');

        $score = RouteLearner::compare('/login', $field, 'union select');

        $this->assertGreaterThanOrEqual(12, $score);
    }

    public function testAllowsExpectedOpaquePasswordShape(): void
    {
        $field = new RequestField('post.password', 'correct horse battery staple', 'post');

        $score = RouteLearner::compare('/login', $field, 'correct horse battery staple');

        $this->assertSame(0, $score);
    }

    public function testUnknownRouteDoesNotScore(): void
    {
        $field = new RequestField('post.username', 'union select', 'post');

        $score = RouteLearner::compare('/unknown', $field, 'union select');

        $this->assertSame(0, $score);
    }
}
