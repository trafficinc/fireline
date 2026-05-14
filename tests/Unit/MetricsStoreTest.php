<?php

use Fireline\Telemetry\MetricsStore;
use Fireline\Telemetry\RuleMetrics;
use PHPUnit\Framework\TestCase;

class MetricsStoreTest extends TestCase
{
    protected $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/fireline-metrics-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testWritesAndReadsPersistedMetrics(): void
    {
        $this->assertTrue(MetricsStore::write($this->path, RuleMetrics::snapshotFrom(
            ['cache.safe.hit' => 1, 'cache.safe.miss' => 3],
            ['scanner.aho_corasick' => ['count' => 1, 'total_ms' => 0.5, 'max_ms' => 0.5]]
        )));

        $snapshot = MetricsStore::read($this->path);

        $this->assertSame(1, $snapshot['counters']['cache.safe.hit']);
        $this->assertSame(0.25, $snapshot['cache_hit_ratios']['safe']);
        $this->assertSame(1, $snapshot['timings']['scanner.aho_corasick']['count']);
    }

    public function testMergesSnapshots(): void
    {
        MetricsStore::write($this->path, RuleMetrics::snapshotFrom(
            ['rule.SQL_UNION_SELECT.matched' => 1],
            ['rule.SQL_UNION_FROM' => ['count' => 1, 'total_ms' => 1.0, 'max_ms' => 1.0]]
        ));

        MetricsStore::write($this->path, RuleMetrics::snapshotFrom(
            ['rule.SQL_UNION_SELECT.matched' => 2],
            ['rule.SQL_UNION_FROM' => ['count' => 1, 'total_ms' => 2.0, 'max_ms' => 2.0]]
        ));

        $snapshot = MetricsStore::read($this->path);

        $this->assertSame(3, $snapshot['counters']['rule.SQL_UNION_SELECT.matched']);
        $this->assertSame(2, $snapshot['timings']['rule.SQL_UNION_FROM']['count']);
        $this->assertEqualsWithDelta(3.0, $snapshot['timings']['rule.SQL_UNION_FROM']['total_ms'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $snapshot['timings']['rule.SQL_UNION_FROM']['max_ms'], 0.0001);
    }

    public function testResetsPersistedMetrics(): void
    {
        MetricsStore::write($this->path, RuleMetrics::snapshotFrom([
            'cache.safe.hit' => 2,
        ], []));

        $this->assertTrue(MetricsStore::reset($this->path));

        $snapshot = MetricsStore::read($this->path);

        $this->assertSame([], $snapshot['counters']);
        $this->assertSame([], $snapshot['timings']);
    }

    public function testMalformedMetricsFileReadsAsEmptySnapshot(): void
    {
        file_put_contents($this->path, '{broken json');

        $snapshot = MetricsStore::read($this->path);

        $this->assertSame([], $snapshot['counters']);
        $this->assertSame([], $snapshot['timings']);
    }

    public function testWriteTreatsMalformedExistingMetricsAsEmpty(): void
    {
        file_put_contents($this->path, '{broken json');

        $this->assertTrue(MetricsStore::write($this->path, RuleMetrics::snapshotFrom([
            'request_limits.evaluated' => 1,
        ], [])));

        $snapshot = MetricsStore::read($this->path);

        $this->assertSame(1, $snapshot['counters']['request_limits.evaluated']);
    }
}
