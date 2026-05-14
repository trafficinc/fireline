<?php

use Fireline\Engine\BotGuard;
use Fireline\Engine\IpGuard;
use Fireline\Normalize\Normalizer;
use Fireline\Scan\AhoCorasick;
use Fireline\Scan\RegexScanner;
use Fireline\Scan\Trie;
use PHPUnit\Framework\TestCase;

class NewPipelineComponentsTest extends TestCase
{
    public function testNormalizerDecodesEntitiesUrlEncodingAndSqlComments(): void
    {
        $normalizer = new Normalizer();

        $this->assertSame(
            '<script>alert(1)</script>',
            $normalizer->run('%26lt%3Bscript%26gt%3Balert(1)%26lt%3B%2Fscript%26gt%3B')
        );

        $this->assertSame('1 union select', $normalizer->run('1/**/UNION--comment' . "\n" . 'SELECT'));
    }

    public function testKeywordAndConditionalRegexScanning(): void
    {
        AhoCorasick::reset();
        $matches = AhoCorasick::scan('1 union select password from users');

        $this->assertNotEmpty($matches);
        $this->assertGreaterThan(0, RegexScanner::scan('1 union select password from users', $matches));
    }

    public function testRegexScannerReturnsMatchedRuleMetadataForExplainability(): void
    {
        AhoCorasick::reset();
        $matches = AhoCorasick::scan('1 union select password from users');

        $result = RegexScanner::scanDetailed('1 union select password from users', $matches);

        $this->assertGreaterThan(0, $result['score']);
        $this->assertContains('SQL_UNION_FROM', array_column($result['matches'], 'id'));
    }

    public function testTrieReturnsPayloadsAndSuppressesDuplicateRules(): void
    {
        $trie = new Trie();
        $trie->add('union select', ['id' => 'SQL_UNION', 'score' => 8]);
        $trie->add('select', ['id' => 'SQL_SELECT', 'score' => 2]);

        $matches = $trie->search('union select union select');
        $ids = array_column($matches, 'id');

        $this->assertSame(['SQL_UNION', 'SQL_SELECT'], $ids);
    }

    public function testAhoCorasickBootReturnsReusableTrie(): void
    {
        $trie = AhoCorasick::boot();
        $matches = $trie->search('javascript:alert(1)');

        $this->assertContains('XSS_JAVASCRIPT_SCHEME', array_column($matches, 'id'));
    }

    public function testAhoCorasickFiltersRulesByParanoiaLevel(): void
    {
        AhoCorasick::reset();

        $lowMatches = AhoCorasick::scan('<img src=x onerror=alert(1)>', 'low');
        $highMatches = AhoCorasick::scan('<img src=x onerror=alert(1)>', 'high');

        $this->assertNotContains('XSS_EVENT_HANDLER', array_column($lowMatches, 'id'));
        $this->assertContains('XSS_EVENT_HANDLER', array_column($highMatches, 'id'));
    }

    public function testBotGuardBlocksEmptyAndKnownBotUserAgents(): void
    {
        $guard = new BotGuard(['^-?$', '^.+(openbot)']);

        $this->assertFalse($guard->safe(''));
        $this->assertSame('[SystemProduced] Empty User-Agent', $guard->found());

        $guard = new BotGuard(['^-?$', '^.+(openbot)']);
        $this->assertFalse($guard->safe('Mozilla openbot'));
        $this->assertSame('Mozilla openbot', $guard->found());
    }

    public function testIpGuardSupportsBlacklistAndWhitelistModes(): void
    {
        $guard = new IpGuard(['203.0.113.10', '198.51.100.'], ['192.0.2.0/24'], []);

        $this->assertFalse($guard->safe('203.0.113.10', ['ip_by_country' => false, 'whitelist' => false]));
        $this->assertFalse($guard->safe('198.51.100.24', ['ip_by_country' => false, 'whitelist' => false]));
        $this->assertTrue($guard->safe('192.0.2.44', ['ip_by_country' => false, 'whitelist' => true]));
        $this->assertFalse($guard->safe('198.51.100.44', ['ip_by_country' => false, 'whitelist' => true]));
    }
}
