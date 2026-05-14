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
                    'allowed_chars' => 'alnum',
                    'denied_tokens' => ['union', 'select'],
                ],
                'post.password' => [
                    'type' => 'opaque',
                    'max_length' => 256,
                ],
                'post.invite_code' => [
                    'shape' => 'A-N',
                    'allowed_chars' => '/\A[a-z]+-\d+\z/i',
                ],
                'post.action' => [
                    'required_tokens' => ['login'],
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

    public function testScoresDeniedTokensForRouteField(): void
    {
        $field = new RequestField('post.username', 'admin union select', 'post');

        $score = RouteLearner::compare('/login', $field, 'admin union select');

        $this->assertGreaterThanOrEqual(20, $score);
    }

    public function testScoresAllowedCharacterAndShapeMismatches(): void
    {
        $field = new RequestField('post.invite_code', 'abc<script>', 'post');

        $score = RouteLearner::compare('/login', $field, 'abc<script>');

        $this->assertGreaterThanOrEqual(12, $score);
    }

    public function testScoresMissingRequiredTokens(): void
    {
        $field = new RequestField('post.action', 'reset password', 'post');

        $score = RouteLearner::compare('/login', $field, 'reset password');

        $this->assertSame(3, $score);
    }
}
