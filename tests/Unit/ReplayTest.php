<?php

use Fireline\Engine\Decision;
use Fireline\Engine\RequestContext;
use Fireline\Engine\WafEngine;
use Fireline\Replay\ReplayRecorder;
use Fireline\Replay\ReplayRunner;
use PHPUnit\Framework\TestCase;

class ReplayTest extends TestCase
{
    protected $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/fireline-replay-' . uniqid('', true) . '.ndjson';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testRecorderStoresNormalizedRequestMatchedRulesDecisionAndScore(): void
    {
        $context = new RequestContext([
            'ip' => '192.0.2.10',
            'method' => 'GET',
            'route' => '/search',
            'uri' => '/search?q=test',
            'user_agent' => 'Mozilla',
        ]);
        $context->addResult([
            'field' => 'get.q',
            'source' => 'get',
            'value' => '1 UNION SELECT',
            'normalized' => '1 union select',
            'score' => 30,
            'matches' => [
                ['id' => 'SQL_UNION_SELECT'],
            ],
            'breakdown' => [
                'rule:SQL_UNION_SELECT' => 8,
            ],
        ]);

        $this->assertTrue((new ReplayRecorder($this->path))->record(Decision::block($context, 'field_score_threshold')));

        $event = json_decode(trim(file_get_contents($this->path)), true);

        $this->assertSame('1 union select', $event['results'][0]['normalized']);
        $this->assertSame(['SQL_UNION_SELECT'], $event['results'][0]['matched_rules']);
        $this->assertTrue($event['decision']['blocked']);
        $this->assertSame(30, $event['decision']['score']);
    }

    public function testRecorderRedactsSensitiveFields(): void
    {
        $context = new RequestContext([
            'ip' => '192.0.2.10',
            'method' => 'POST',
            'route' => '/login',
            'uri' => '/login',
            'user_agent' => "Mozilla\nInjected",
        ]);
        $context->addResult([
            'field' => 'post.password',
            'source' => 'post',
            'value' => 'correct horse battery staple',
            'normalized' => 'correct horse battery staple',
            'score' => 0,
            'matches' => [],
            'breakdown' => [],
        ]);

        $this->assertTrue((new ReplayRecorder($this->path))->record(Decision::allow($context)));

        $event = json_decode(trim(file_get_contents($this->path)), true);

        $this->assertSame('[redacted]', $event['results'][0]['value']);
        $this->assertSame('[redacted]', $event['results'][0]['normalized']);
        $this->assertTrue($event['results'][0]['redacted']);
        $this->assertSame('Mozilla Injected', $event['request']['user_agent']);
    }

    public function testRunnerReplaysStoredNormalizedTrafficAgainstCurrentEngine(): void
    {
        $event = [
            'request' => [
                'route' => '/search',
                'method' => 'GET',
                'uri' => '/search?q=test',
            ],
            'results' => [
                [
                    'field' => 'get.q',
                    'source' => 'get',
                    'value' => '1 union select password from users',
                    'normalized' => '1 union select password from users',
                    'score' => 1,
                    'matches' => [],
                    'breakdown' => [],
                ],
            ],
            'decision' => [
                'blocked' => false,
                'reason' => 'allowed',
                'score' => 1,
            ],
        ];
        file_put_contents($this->path, json_encode($event) . PHP_EOL);

        $result = (new ReplayRunner(new WafEngine([
            'replay_enabled' => false,
            'score_threshold' => 25,
            'regex_threshold' => 10,
        ])))->replay($this->path);

        $this->assertSame(1, $result['total']);
        $this->assertSame('new_block', $result['regressions'][0]['type']);
        $this->assertSame(1, $result['summary']['by_type']['new_block']);
        $this->assertSame(1, $result['summary']['by_route']['/search']);
        $this->assertGreaterThanOrEqual(25, $result['regressions'][0]['current_score']);
    }

    public function testRunnerReportsPreviouslyBlockedTrafficThatIsNowAllowed(): void
    {
        $event = [
            'request' => [
                'route' => '/search',
                'method' => 'GET',
                'uri' => '/search?q=test',
            ],
            'results' => [
                [
                    'field' => 'get.q',
                    'source' => 'get',
                    'value' => 'stainless washers',
                    'normalized' => 'stainless washers',
                    'score' => 30,
                    'matches' => [
                        ['id' => 'OLD_RULE'],
                    ],
                    'breakdown' => [
                        'rule:OLD_RULE' => 30,
                    ],
                ],
            ],
            'decision' => [
                'blocked' => true,
                'reason' => 'field_score_threshold',
                'score' => 30,
            ],
        ];
        file_put_contents($this->path, json_encode($event) . PHP_EOL);

        $result = (new ReplayRunner(new WafEngine([
            'replay_enabled' => false,
            'score_threshold' => 25,
            'regex_threshold' => 10,
        ])))->replay($this->path);

        $this->assertSame('missed_block', $result['regressions'][0]['type']);
        $this->assertTrue($result['regressions'][0]['previous_blocked']);
        $this->assertFalse($result['regressions'][0]['current_blocked']);
    }

    public function testRunnerReportsInvalidReplayLines(): void
    {
        file_put_contents($this->path, "{not-json}\n");

        $result = (new ReplayRunner(new WafEngine(['replay_enabled' => false])))->replay($this->path);

        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['invalid']);
        $this->assertSame(1, $result['summary']['invalid']);
    }
}
