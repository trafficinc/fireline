<?php

use Fireline\Telemetry\MetricsFormatter;
use PHPUnit\Framework\TestCase;

class MetricsFormatterTest extends TestCase
{
    public function testFormatsSnapshotAsText(): void
    {
        $text = MetricsFormatter::text([
            'counters' => [
                'rule.SQL_UNION_SELECT.matched' => 2,
            ],
            'cache_hit_ratios' => [
                'safe' => 0.5,
            ],
            'timings' => [
                'scanner.aho_corasick' => [
                    'count' => 1,
                    'total_ms' => 0.1234,
                    'max_ms' => 0.1234,
                ],
            ],
            'slowest_rules' => [],
        ]);

        $this->assertStringContainsString('Metrics snapshot', $text);
        $this->assertStringContainsString('rule.SQL_UNION_SELECT.matched: 2', $text);
        $this->assertStringContainsString('safe: 0.5', $text);
    }

    public function testFormatsSnapshotAsJson(): void
    {
        $json = MetricsFormatter::json([
            'counters' => [
                'cache.safe.hit' => 1,
            ],
        ]);

        $decoded = json_decode($json, true);

        $this->assertSame(1, $decoded['counters']['cache.safe.hit']);
    }
}
