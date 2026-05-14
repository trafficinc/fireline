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
        $this->assertGreaterThanOrEqual(25, $result['regressions'][0]['current_score']);
    }
}
