<?php

use Fireline\Cache\FingerprintCache;
use Fireline\Cache\SafeCache;
use Fireline\Cache\ThreatCache;
use Fireline\Engine\FieldInspector;
use Fireline\Extract\RequestField;
use Fireline\Scoring\Thresholds;
use PHPUnit\Framework\TestCase;

class FieldInspectorTest extends TestCase
{
    protected function setUp(): void
    {
        SafeCache::reset();
        ThreatCache::reset();
    }

    public function testInspectsFieldAndReturnsScanResult(): void
    {
        $inspector = new FieldInspector(new Thresholds([
            'paranoia_level' => 'medium',
            'regex_threshold' => 10,
        ]));

        $result = $inspector->inspect(
            ['method' => 'GET', 'route' => '/search'],
            new RequestField('get.q', '1 union select password from users', 'get'),
            '1 union select password from users',
            false
        );

        $this->assertSame('get.q', $result->toArray()['field']);
        $this->assertGreaterThan(0, $result->score());
        $this->assertNotSame('', $result->fingerprint());
        $this->assertContains('SQL_UNION_FROM', array_column($result->toArray()['matches'], 'id'));
        $this->assertArrayHasKey('rule:SQL_UNION_FROM', $result->toArray()['breakdown']);
    }

    public function testInspectsUploadMetadataWithUploadHeuristics(): void
    {
        $inspector = new FieldInspector(new Thresholds([
            'paranoia_level' => 'medium',
            'regex_threshold' => 10,
        ]));

        $result = $inspector->inspect(
            ['method' => 'POST', 'route' => '/upload'],
            new RequestField('file.avatar.name', 'avatar.php', 'file'),
            'avatar.php',
            false
        );

        $this->assertArrayHasKey('upload_heuristics', $result->toArray()['breakdown']);
        $this->assertContains('UPLOAD_PHP_EXTENSION', array_column($result->toArray()['matches'], 'id'));
        $this->assertGreaterThanOrEqual(22, $result->score());
    }

    public function testKnownThreatFingerprintShortCircuitsInspection(): void
    {
        $request = ['method' => 'GET', 'route' => '/search'];
        $field = new RequestField('get.q', 'abc123', 'get');
        $normalized = 'abc123';
        ThreatCache::remember(FingerprintCache::build($request, $field, $normalized));

        $inspector = new FieldInspector(new Thresholds([
            'paranoia_level' => 'medium',
            'score_threshold' => 25,
            'regex_threshold' => 10,
        ]));

        $result = $inspector->inspect($request, $field, $normalized, true);

        $this->assertSame(25, $result->score());
        $this->assertSame(['threat_cache' => 25], $result->toArray()['breakdown']);
        $this->assertSame('THREAT_CACHE_HIT', $result->toArray()['matches'][0]['id']);
    }

    public function testThreatCacheTakesPrecedenceOverSafeCache(): void
    {
        $request = ['method' => 'GET', 'route' => '/search'];
        $field = new RequestField('get.q', 'abc123', 'get');
        $normalized = 'abc123';
        $fingerprint = FingerprintCache::build($request, $field, $normalized);
        SafeCache::remember($fingerprint);
        ThreatCache::remember($fingerprint);

        $inspector = new FieldInspector(new Thresholds([
            'paranoia_level' => 'medium',
            'score_threshold' => 25,
            'regex_threshold' => 10,
        ]));

        $result = $inspector->inspect($request, $field, $normalized, true);

        $this->assertNotNull($result);
        $this->assertSame('THREAT_CACHE_HIT', $result->toArray()['matches'][0]['id']);
    }
}
