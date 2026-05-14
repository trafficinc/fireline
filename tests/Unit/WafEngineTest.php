<?php

use Fireline\Cache\SafeCache;
use Fireline\Cache\ThreatCache;
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
        SafeCache::reset();
        ThreatCache::reset();
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

    public function testRepeatedThreatFingerprintBlocksFromCache(): void
    {
        $_GET['id'] = '1 union select password from users';
        (new WafEngine())->inspectCurrentRequest();

        $decision = (new WafEngine())->inspectCurrentRequest();

        $this->assertTrue($decision->shouldBlock());
        $this->assertSame('field_score_threshold', $decision->reason());
        $this->assertSame(['threat_cache' => 25], $decision->matchedResult()['breakdown']);
        $this->assertSame('THREAT_CACHE_HIT', $decision->matchedResult()['matches'][0]['id']);
    }

    public function testSafeCacheDoesNotHideThreatGrammarWithSameTextShape(): void
    {
        $_GET['q'] = 'stainless steel washer product catalog';
        $allowed = (new WafEngine())->inspectCurrentRequest();
        $this->assertFalse($allowed->shouldBlock());

        $_GET['q'] = 'union select password from users';
        $decision = (new WafEngine())->inspectCurrentRequest();

        $this->assertTrue($decision->shouldBlock());
        $this->assertNotSame([], $decision->matchedResult());
        $this->assertNotSame(['threat_cache' => 25], $decision->matchedResult()['breakdown']);
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
        $this->assertSame(['bot_guard' => 25], $decision->matchedResult()['breakdown']);
    }

    public function testBlocksRequestBeforeScanningWhenLimitsAreExceeded(): void
    {
        $_GET = [
            'a' => '1',
            'b' => '2',
        ];

        $decision = (new WafEngine(['max_fields' => 1]))->inspectCurrentRequest();

        $this->assertTrue($decision->shouldBlock());
        $this->assertSame('request_limit', $decision->reason());
        $this->assertSame('REQUEST_LIMIT_MAX_FIELDS', $decision->matchedResult()['matches'][0]['id']);
    }
}
