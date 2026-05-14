<?php

use Fireline\Cache\FingerprintCache;
use Fireline\Extract\RequestField;
use PHPUnit\Framework\TestCase;

class FingerprintCacheTest extends TestCase
{
    public function testPreservesThreatSignalsInShapeFingerprint(): void
    {
        $request = ['method' => 'GET', 'route' => '/search'];
        $field = new RequestField('get.q', '', 'get');

        $benign = FingerprintCache::build($request, $field, 'stainless washers');
        $sqli = FingerprintCache::build($request, $field, 'union select');

        $this->assertNotSame($benign, $sqli);
    }

    public function testBenignValuesWithSameShapeShareFingerprint(): void
    {
        $request = ['method' => 'GET', 'route' => '/search'];
        $field = new RequestField('get.q', '', 'get');

        $first = FingerprintCache::build($request, $field, 'stainless washers');
        $second = FingerprintCache::build($request, $field, 'brass screws');

        $this->assertSame($first, $second);
    }
}
