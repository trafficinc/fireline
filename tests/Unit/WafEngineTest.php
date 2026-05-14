<?php

use Fireline\Engine\WafEngine;
use PHPUnit\Framework\TestCase;

class WafEngineTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [
            'REMOTE_ADDR' => '192.0.2.10',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/search?q=test',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
    }

    public function testAllowsBenignRequest(): void
    {
        $_GET['q'] = 'stainless steel washers';

        $decision = (new WafEngine())->inspectCurrentRequest();

        $this->assertFalse($decision->shouldBlock());
    }

    public function testBlocksSqlInjectionRequest(): void
    {
        $_GET['id'] = '1 union select password from users';

        $decision = (new WafEngine())->inspectCurrentRequest();

        $this->assertTrue($decision->shouldBlock());
        $this->assertSame('field_score_threshold', $decision->reason());
        $this->assertSame('get.id', $decision->matchedResult()['field']);
        $this->assertSame('get', $decision->matchedResult()['source']);
        $this->assertGreaterThanOrEqual(25, $decision->matchedResult()['score']);
    }

    public function testBlocksXssRequest(): void
    {
        $_POST['comment'] = '<script>alert(1)</script>';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $decision = (new WafEngine())->inspectCurrentRequest();

        $this->assertTrue($decision->shouldBlock());
    }

    public function testBlocksBotUserAgentThroughCompatibilityGuard(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = '';

        $decision = (new WafEngine())->inspectCurrentRequest();

        $this->assertTrue($decision->shouldBlock());
        $this->assertSame('bot', $decision->reason());
    }
}
