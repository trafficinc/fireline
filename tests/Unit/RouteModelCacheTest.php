<?php

use Fireline\Cache\RouteModelCache;
use Fireline\Telemetry\RuleMetrics;
use PHPUnit\Framework\TestCase;

class RouteModelCacheTest extends TestCase
{
    protected function setUp(): void
    {
        RouteModelCache::reset();
        RuleMetrics::reset();
    }

    public function testStoresAndFetchesRouteModels(): void
    {
        $this->assertNull(RouteModelCache::get('/login'));

        RouteModelCache::put('/login', [
            'fields' => [
                'post.username' => ['type' => 'alnum'],
            ],
        ]);

        $model = RouteModelCache::get('/login');

        $this->assertSame('alnum', $model['fields']['post.username']['type']);
    }

    public function testTracksCacheMetrics(): void
    {
        RouteModelCache::get('/missing');
        RouteModelCache::put('/search', ['fields' => []]);
        RouteModelCache::get('/search');

        $snapshot = RuleMetrics::snapshot();

        $this->assertSame(1, $snapshot['counters']['cache.route_model.write']);
        $this->assertSame(0.5, $snapshot['cache_hit_ratios']['route_model']);
    }
}
