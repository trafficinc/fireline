<?php

use Fireline\Cache\SafeCache;
use Fireline\Cache\ThreatCache;
use Fireline\Scan\AhoCorasick;
use Fireline\Scan\RegexScanner;
use Fireline\Telemetry\RuleMetrics;
use PHPUnit\Framework\TestCase;

class RuleMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        RuleMetrics::reset();
        RuleMetrics::enable(true);
        SafeCache::reset();
        ThreatCache::reset();
        AhoCorasick::reset();
    }

    public function testTracksRuleExecutionCountsAndTimings(): void
    {
        $matches = AhoCorasick::scan('1 union select password from users');
        RegexScanner::scan('1 union select password from users', $matches);

        $snapshot = RuleMetrics::snapshot();

        $this->assertGreaterThan(0, $snapshot['counters']['rule.SQL_UNION_SELECT.matched'] ?? 0);
        $this->assertGreaterThan(0, $snapshot['counters']['rule.SQL_UNION_FROM.executed'] ?? 0);
        $this->assertArrayHasKey('rule.SQL_UNION_FROM', $snapshot['timings']);
        $this->assertArrayHasKey('scanner.aho_corasick', $snapshot['timings']);
    }

    public function testTracksCacheHitRatios(): void
    {
        $fingerprint = sha1('safe');

        $this->assertFalse(SafeCache::isKnownSafe($fingerprint));
        SafeCache::remember($fingerprint);
        $this->assertTrue(SafeCache::isKnownSafe($fingerprint));

        $snapshot = RuleMetrics::snapshot();

        $this->assertSame(0.5, $snapshot['cache_hit_ratios']['safe']);
    }

    public function testTracksThreatCacheMetricsAndReset(): void
    {
        $fingerprint = sha1('threat');

        ThreatCache::remember($fingerprint);
        $this->assertTrue(ThreatCache::isKnownThreat($fingerprint));

        ThreatCache::reset();

        $this->assertFalse(ThreatCache::isKnownThreat($fingerprint));
        $snapshot = RuleMetrics::snapshot();

        $this->assertSame(1, $snapshot['counters']['cache.threat.write']);
        $this->assertArrayHasKey('threat', $snapshot['cache_hit_ratios']);
    }

    public function testTracksFalsePositiveCounts(): void
    {
        RuleMetrics::falsePositive('SQL_UNION_SELECT');

        $snapshot = RuleMetrics::snapshot();

        $this->assertSame(1, $snapshot['counters']['rule.SQL_UNION_SELECT.false_positive']);
    }

    public function testCanDisableMetrics(): void
    {
        RuleMetrics::enable(false);
        RuleMetrics::increment('rule.TEST.executed');

        $this->assertSame([], RuleMetrics::snapshot()['counters']);
    }

    public function testSnapshotFromNormalizesExternalMetricData(): void
    {
        $snapshot = RuleMetrics::snapshotFrom([
            'cache.safe.hit' => '2',
            'cache.safe.miss' => '2',
        ], [
            'rule.fast' => [
                'count' => '1',
                'total_ms' => '0.5',
                'max_ms' => '0.5',
            ],
            'broken' => 'not-a-timing',
            'rule.slow' => [
                'count' => '1',
                'total_ms' => '3.0',
                'max_ms' => '3.0',
            ],
        ]);

        $this->assertSame(2, $snapshot['counters']['cache.safe.hit']);
        $this->assertSame(0.5, $snapshot['cache_hit_ratios']['safe']);
        $this->assertArrayNotHasKey('broken', $snapshot['timings']);
        $this->assertSame(['rule.slow', 'rule.fast'], array_keys($snapshot['slowest_rules']));
    }
}
