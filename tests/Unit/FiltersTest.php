<?php

use Filters\BOTS;
use Filters\IP;
use Filters\Query;
use Filters\SQL;
use Filters\XSS;
use PHPUnit\Framework\TestCase;

class TestableIPFilter extends IP
{
    public function __construct(array $compares = [])
    {
        $this->compares = $compares;
    }
}

class TestableBotFilter extends BOTS
{
    public function __construct(array $compares = [])
    {
        $this->compares = $compares;
    }
}

class FiltersTest extends TestCase
{
    public function testSqlInjectionPatternsAreBlocked(): void
    {
        $filter = new SQL();

        $this->assertFalse($filter->safe('1 union select password from users', []));
        $this->assertFalse($filter->safe('admin" or 1=1 --', []));
        $this->assertFalse($filter->safe('sleep(5)', []));
    }

    public function testBenignSqlLikeInputIsAllowed(): void
    {
        $filter = new SQL();

        $this->assertTrue($filter->safe('stainless steel bolt 1/4 inch', []));
        $this->assertTrue($filter->safe('order history for customer 123', []));
    }

    public function testSqlFilterDoesNotBlockXssOnlyPayloads(): void
    {
        $filter = new SQL();

        $this->assertTrue($filter->safe('<script>alert(1)</script>', []));
    }

    public function testXssPatternsAreBlocked(): void
    {
        $filter = new XSS();

        $this->assertFalse($filter->safe('<script>alert(1)</script>', []));
        $this->assertFalse($filter->safe('<img src=x onerror=alert(1)>', []));
        $this->assertFalse($filter->safe('javascript:alert(1)', []));
    }

    public function testBenignHtmlLikeTextIsAllowed(): void
    {
        $filter = new XSS();

        $this->assertTrue($filter->safe('price is < 10 and quantity > 2', []));
        $this->assertTrue($filter->safe('support@example.com', []));
    }

    public function testXssFilterDoesNotBlockSqlOnlyPayloads(): void
    {
        $filter = new XSS();

        $this->assertTrue($filter->safe('1 union select password from users', []));
    }

    public function testBotDetectionBlocksKnownAndEmptyAgents(): void
    {
        $filter = new BOTS();

        $this->assertFalse($filter->safe('Mozilla/5.0 openbot crawler', []));
        $this->assertFalse($filter->safe('', []));
    }

    public function testBotDetectionAllowsNormalUserAgent(): void
    {
        $filter = new BOTS();

        $this->assertTrue($filter->safe('Mozilla/5.0 AppleWebKit/537.36 Chrome/120 Safari/537.36', []));
    }

    public function testBotDetectionPreservesFoundValueForCompatibility(): void
    {
        $filter = new TestableBotFilter(['^-?$', '^.+(openbot)']);

        $this->assertFalse($filter->safe('Mozilla openbot', []));
        $this->assertSame('Mozilla openbot', $filter->getFound());

        $filter = new TestableBotFilter(['^-?$']);
        $this->assertFalse($filter->safe('', []));
        $this->assertSame('[SystemProduced] Empty User-Agent', $filter->getFound());
    }

    public function testQueryFilteringBlocksExploitPatterns(): void
    {
        $filter = new Query();

        $this->assertFalse($filter->safe('cmd=/bin/sh', []));
        $this->assertFalse($filter->safe('q=javascript:alert(1)', []));
        $this->assertFalse($filter->safe('file=/etc/passwd', []));
    }

    public function testQueryFilteringAllowsCommonBenignValues(): void
    {
        $filter = new Query();

        $this->assertTrue($filter->safe('asset=app.js', []));
        $this->assertTrue($filter->safe('format=txt', []));
        $this->assertTrue($filter->safe('q=union hardware', []));
    }

    public function testIpBlacklistBlocksExactAndPartialMatches(): void
    {
        $filter = new TestableIPFilter(['203.0.113.10', '198.51.100.']);

        $this->assertFalse($filter->safe('203.0.113.10', ['ip_by_country' => false, 'whitelist' => false]));
        $this->assertFalse($filter->safe('198.51.100.25', ['ip_by_country' => false, 'whitelist' => false]));
    }

    public function testIpBlacklistAllowsUnlistedIp(): void
    {
        $filter = new TestableIPFilter(['203.0.113.10']);

        $this->assertTrue($filter->safe('192.0.2.20', ['ip_by_country' => false, 'whitelist' => false]));
    }

    public function testIpCidrCheckSupportsWhitelistStyleRanges(): void
    {
        $filter = new TestableIPFilter();

        $this->assertTrue($filter->ipCIDRCheck('192.0.2.25', '192.0.2.0/24'));
        $this->assertFalse($filter->ipCIDRCheck('198.51.100.25', '192.0.2.0/24'));
        $this->assertFalse($filter->ipCIDRCheck('bad-ip', '192.0.2.0/24'));
    }
}
