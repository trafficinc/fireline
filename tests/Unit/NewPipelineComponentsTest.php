<?php

use Fireline\Engine\BotGuard;
use Fireline\Engine\IpGuard;
use Fireline\Heuristics\EntropyHeuristics;
use Fireline\Heuristics\UploadHeuristics;
use Fireline\Normalize\Normalizer;
use Fireline\Extract\MultipartExtractor;
use Fireline\Extract\RequestField;
use Fireline\Extract\RequestExtractor;
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

    public function testEntropyHeuristicsScoreEncodedLookingPayloadsWithoutScoringPlainText(): void
    {
        $plain = str_repeat('stainless steel washers ', 8);
        $encoded = str_repeat('QWxhZGRpbjpvcGVuIHNlc2FtZTEyMzQ1Njc4OTA=', 4);

        $this->assertSame(0, EntropyHeuristics::analyze($plain));
        $this->assertGreaterThanOrEqual(3, EntropyHeuristics::analyze($encoded));
        $this->assertGreaterThan(3.0, EntropyHeuristics::shannonEntropy($encoded));
    }

    public function testMultipartExtractorNormalizesUploadMetadataWithoutTempPaths(): void
    {
        $fields = MultipartExtractor::extract([
            'avatar' => [
                'name' => 'shell.php',
                'type' => 'application/x-php',
                'tmp_name' => '/tmp/php123',
                'error' => 0,
                'size' => 512,
            ],
        ]);

        $this->assertSame('shell.php', $fields['avatar']['name']);
        $this->assertSame('application/x-php', $fields['avatar']['type']);
        $this->assertSame('512', $fields['avatar']['size']);
        $this->assertArrayNotHasKey('tmp_name', $fields['avatar']);
    }

    public function testUploadHeuristicsScoreDangerousUploadMetadata(): void
    {
        $filename = new RequestField('file.avatar.name', 'avatar.php', 'file');
        $mime = new RequestField('file.avatar.type', 'application/x-httpd-php', 'file');
        $normal = new RequestField('post.avatar', 'avatar.php', 'post');

        $this->assertGreaterThanOrEqual(22, UploadHeuristics::analyze($filename, 'avatar.php'));
        $this->assertSame(18, UploadHeuristics::analyze($mime, 'application/x-httpd-php'));
        $this->assertSame(0, UploadHeuristics::analyze($normal, 'avatar.php'));
    }

    public function testRequestExtractorAddsMultipartFileFields(): void
    {
        $_SERVER = [
            'REMOTE_ADDR' => '192.0.2.10',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/upload',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'CONTENT_TYPE' => 'multipart/form-data; boundary=abc',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [
            'avatar' => [
                'name' => 'shell.php',
                'type' => 'application/x-php',
                'tmp_name' => '/tmp/php123',
                'error' => 0,
                'size' => 512,
            ],
        ];

        $request = (new RequestExtractor([
            'trusted_proxies' => [],
            'inspect_headers' => false,
            'inspect_json' => true,
            'inspect_raw_body' => true,
            'max_value_length' => 8192,
        ]))->capture();

        $fields = [];
        foreach ($request['fields'] as $field) {
            $fields[$field->name()] = $field->value();
        }

        $this->assertSame('shell.php', $fields['file.avatar.name']);
        $this->assertSame('application/x-php', $fields['file.avatar.type']);
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
