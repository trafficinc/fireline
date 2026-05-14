<?php

use PHPUnit\Framework\TestCase;
use Fireline\Telemetry\MetricsStore;
use Fireline\Telemetry\RuleMetrics;

class CliReplayTest extends TestCase
{
    protected $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/fireline-cli-replay-' . uniqid('', true) . '.ndjson';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testHelpListsReplayAndMetricsCommands(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' fire.php help';
        exec($command, $output, $exitCode);

        $text = implode("\n", $output);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('replay:run', $text);
        $this->assertStringContainsString('baseline:build', $text);
        $this->assertStringContainsString('baseline:export', $text);
        $this->assertStringContainsString('metrics:show', $text);
        $this->assertStringContainsString('metrics:export', $text);
        $this->assertStringContainsString('metrics:reset', $text);
    }

    public function testReplayCiModeReturnsZeroWhenNoRegressionsExist(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' fire.php replay:run ' . escapeshellarg($this->path) . ' --ci';
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode);
    }

    public function testReplayCiModeWithoutExplicitPathUsesDefaultReplayPath(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' fire.php replay:run --ci';
        exec($command, $output, $exitCode);

        $text = implode("\n", $output);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('storage/replay/traffic.ndjson', $text);
        $this->assertStringNotContainsString('By type:', $text);
    }

    public function testReplayCiModeReturnsNonZeroWhenRegressionsExist(): void
    {
        file_put_contents($this->path, json_encode([
            'request' => [
                'route' => '/search',
                'method' => 'GET',
                'uri' => '/search?q=test',
            ],
            'results' => [
                [
                    'field' => 'get.q',
                    'source' => 'get',
                    'value' => '1 union select password from users',
                    'normalized' => '1 union select password from users',
                    'score' => 1,
                    'matches' => [],
                    'breakdown' => [],
                ],
            ],
            'decision' => [
                'blocked' => false,
                'reason' => 'allowed',
                'score' => 1,
            ],
        ]) . PHP_EOL);

        $command = escapeshellarg(PHP_BINARY) . ' fire.php replay:run ' . escapeshellarg($this->path) . ' --ci';
        exec($command, $output, $exitCode);

        $this->assertSame(1, $exitCode);
    }

    public function testReplayRunCanOutputJson(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' fire.php replay:run ' . escapeshellarg($this->path) . ' --json';
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode);
        $decoded = json_decode(implode("\n", $output), true);
        $this->assertIsArray($decoded);
        $this->assertSame(0, $decoded['total']);
        $this->assertArrayHasKey('regressions', $decoded);
    }

    public function testReplayRunJsonKeepsCiExitCode(): void
    {
        file_put_contents($this->path, json_encode([
            'request' => [
                'route' => '/search',
                'method' => 'GET',
                'uri' => '/search?q=test',
            ],
            'results' => [
                [
                    'field' => 'get.q',
                    'source' => 'get',
                    'value' => '1 union select password from users',
                    'normalized' => '1 union select password from users',
                    'score' => 1,
                    'matches' => [],
                    'breakdown' => [],
                ],
            ],
            'decision' => [
                'blocked' => false,
                'reason' => 'allowed',
                'score' => 1,
            ],
        ]) . PHP_EOL);

        $command = escapeshellarg(PHP_BINARY) . ' fire.php replay:run ' . escapeshellarg($this->path) . ' --json --ci';
        exec($command, $output, $exitCode);

        $this->assertSame(1, $exitCode);
        $decoded = json_decode(implode("\n", $output), true);
        $this->assertIsArray($decoded);
        $this->assertSame('new_block', $decoded['regressions'][0]['type']);
    }

    public function testReplayRunJsonIncludesMetadataDiffs(): void
    {
        file_put_contents($this->path, json_encode([
            'metadata' => [
                'paranoia_level' => 'low',
                'thresholds' => [
                    'score_threshold' => 35,
                    'regex_threshold' => 15,
                    'safe_cache_threshold' => 2,
                ],
                'config' => [
                    'inspect_json' => true,
                    'inspect_headers' => true,
                    'inspect_raw_body' => true,
                    'max_fields' => 200,
                    'max_headers' => 100,
                    'max_header_length' => 8192,
                    'max_body_length' => 1048576,
                    'max_value_length' => 8192,
                ],
                'rules' => [
                    'count' => 1,
                    'fingerprint' => str_repeat('a', 40),
                ],
            ],
            'request' => [
                'route' => '/search',
                'method' => 'GET',
                'uri' => '/search?q=test',
            ],
            'results' => [
                [
                    'field' => 'get.q',
                    'source' => 'get',
                    'value' => '1 union select password from users',
                    'normalized' => '1 union select password from users',
                    'score' => 1,
                    'matches' => [],
                    'breakdown' => [],
                ],
            ],
            'decision' => [
                'blocked' => false,
                'reason' => 'allowed',
                'score' => 1,
            ],
        ]) . PHP_EOL);

        $command = escapeshellarg(PHP_BINARY) . ' fire.php replay:run ' . escapeshellarg($this->path) . ' --json';
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode);
        $decoded = json_decode(implode("\n", $output), true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['regressions'][0]['metadata_changed']);
        $this->assertContains('rules', $decoded['regressions'][0]['metadata_diff']['changed']);
    }

    public function testReplayRunTextIncludesMetadataDiffs(): void
    {
        file_put_contents($this->path, json_encode([
            'metadata' => [
                'paranoia_level' => 'low',
                'thresholds' => [
                    'score_threshold' => 35,
                    'regex_threshold' => 15,
                    'safe_cache_threshold' => 2,
                ],
                'rules' => [
                    'count' => 1,
                    'fingerprint' => str_repeat('a', 40),
                ],
            ],
            'request' => [
                'route' => '/search',
                'method' => 'GET',
                'uri' => '/search?q=test',
            ],
            'results' => [
                [
                    'field' => 'get.q',
                    'source' => 'get',
                    'value' => '1 union select password from users',
                    'normalized' => '1 union select password from users',
                    'score' => 1,
                    'matches' => [],
                    'breakdown' => [],
                ],
            ],
            'decision' => [
                'blocked' => false,
                'reason' => 'allowed',
                'score' => 1,
            ],
        ]) . PHP_EOL);

        $command = escapeshellarg(PHP_BINARY) . ' fire.php replay:run ' . escapeshellarg($this->path);
        exec($command, $output, $exitCode);

        $text = implode("\n", $output);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Metadata Changed: yes', $text);
        $this->assertStringContainsString('Metadata Diff:', $text);
        $this->assertStringContainsString('rules', $text);
    }

    public function testBaselineBuildCanOutputJson(): void
    {
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '100')) . PHP_EOL);
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '101')) . PHP_EOL, FILE_APPEND);
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '102')) . PHP_EOL, FILE_APPEND);

        $command = escapeshellarg(PHP_BINARY) . ' fire.php baseline:build ' . escapeshellarg($this->path) . ' 3 --json';
        exec($command, $output, $exitCode);

        $decoded = json_decode(implode("\n", $output), true);
        $this->assertSame(0, $exitCode);
        $this->assertIsArray($decoded);
        $this->assertSame('int', $decoded['/orders']['fields']['get.id']['type']);
    }

    public function testBaselineBuildCanOutputJsonReport(): void
    {
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '100')) . PHP_EOL);
        file_put_contents($this->path, '{bad json' . PHP_EOL, FILE_APPEND);
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '101')) . PHP_EOL, FILE_APPEND);
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '102')) . PHP_EOL, FILE_APPEND);

        $command = escapeshellarg(PHP_BINARY) . ' fire.php baseline:build ' . escapeshellarg($this->path) . ' 3 --json --report';
        exec($command, $output, $exitCode);

        $decoded = json_decode(implode("\n", $output), true);
        $this->assertSame(0, $exitCode);
        $this->assertIsArray($decoded);
        $this->assertSame(4, $decoded['total']);
        $this->assertSame(1, $decoded['invalid']);
        $this->assertSame('int', $decoded['model']['/orders']['fields']['get.id']['type']);
    }

    public function testBaselineExportWritesPhpRouteModel(): void
    {
        $destination = sys_get_temp_dir() . '/fireline-routes-' . uniqid('', true) . '.php';
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '100')) . PHP_EOL);
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '101')) . PHP_EOL, FILE_APPEND);
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '102')) . PHP_EOL, FILE_APPEND);

        try {
            $command = escapeshellarg(PHP_BINARY) . ' fire.php baseline:export ' . escapeshellarg($this->path) . ' 3 ' . escapeshellarg($destination);
            exec($command, $output, $exitCode);

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Route model exported:', implode("\n", $output));
            $this->assertFileExists($destination);

            $model = require $destination;
            $this->assertSame('int', $model['/orders']['fields']['get.id']['type']);
        } finally {
            if (is_file($destination)) {
                unlink($destination);
            }
        }
    }

    public function testBaselineExportDryRunDoesNotWriteFile(): void
    {
        $destination = sys_get_temp_dir() . '/fireline-routes-' . uniqid('', true) . '.php';
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '100')) . PHP_EOL);
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '101')) . PHP_EOL, FILE_APPEND);
        file_put_contents($this->path, json_encode($this->replayEvent('/orders', 'get.id', '102')) . PHP_EOL, FILE_APPEND);

        $command = escapeshellarg(PHP_BINARY) . ' fire.php baseline:export ' . escapeshellarg($this->path) . ' 3 ' . escapeshellarg($destination) . ' --dry-run';
        exec($command, $output, $exitCode);

        $text = implode("\n", $output);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Route model export preview:', $text);
        $this->assertStringContainsString('Events read: 3', $text);
        $this->assertFileDoesNotExist($destination);
    }

    public function testMetricsShowDisplaysSnapshot(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' fire.php metrics:show';
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Metrics snapshot', implode("\n", $output));
    }

    public function testMetricsShowCanOutputJson(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' fire.php metrics:show --json';
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode);
        $decoded = json_decode(implode("\n", $output), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('counters', $decoded);
    }

    public function testMetricsShowReadsPersistedMetricsFile(): void
    {
        $path = sys_get_temp_dir() . '/fireline-cli-metrics-' . uniqid('', true) . '.json';
        MetricsStore::write($path, RuleMetrics::snapshotFrom([
            'cache.safe.hit' => 2,
        ], []));

        try {
            $command = escapeshellarg(PHP_BINARY) . ' fire.php metrics:show ' . escapeshellarg($path) . ' --json';
            exec($command, $output, $exitCode);

            $this->assertSame(0, $exitCode);
            $decoded = json_decode(implode("\n", $output), true);
            $this->assertSame(2, $decoded['counters']['cache.safe.hit']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testMetricsResetClearsPersistedMetricsFile(): void
    {
        $path = sys_get_temp_dir() . '/fireline-cli-metrics-' . uniqid('', true) . '.json';
        MetricsStore::write($path, RuleMetrics::snapshotFrom([
            'cache.safe.hit' => 2,
        ], []));

        try {
            $command = escapeshellarg(PHP_BINARY) . ' fire.php metrics:reset ' . escapeshellarg($path);
            exec($command, $output, $exitCode);

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Metrics reset:', implode("\n", $output));

            $snapshot = MetricsStore::read($path);
            $this->assertSame([], $snapshot['counters']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testMetricsShowCanOutputSummary(): void
    {
        $path = sys_get_temp_dir() . '/fireline-cli-metrics-' . uniqid('', true) . '.json';
        MetricsStore::write($path, RuleMetrics::snapshotFrom([
            'cache.safe.hit' => 2,
        ], []));

        try {
            $command = escapeshellarg(PHP_BINARY) . ' fire.php metrics:show ' . escapeshellarg($path) . ' --summary';
            exec($command, $output, $exitCode);

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Metrics summary', implode("\n", $output));
            $this->assertStringContainsString('cache.safe.hit: 2', implode("\n", $output));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testMetricsShowSummaryHandlesMalformedTimingEntries(): void
    {
        $path = sys_get_temp_dir() . '/fireline-cli-metrics-' . uniqid('', true) . '.json';
        file_put_contents($path, json_encode([
            'counters' => [
                'cache.safe.hit' => '2',
                'cache.safe.miss' => '1',
            ],
            'timings' => [
                'broken' => 'bad',
                'scanner.aho_corasick' => [
                    'count' => '1',
                    'total_ms' => '0.25',
                    'max_ms' => '0.25',
                ],
            ],
        ]));

        try {
            $command = escapeshellarg(PHP_BINARY) . ' fire.php metrics:show ' . escapeshellarg($path) . ' --summary';
            exec($command, $output, $exitCode);

            $text = implode("\n", $output);
            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Metrics summary', $text);
            $this->assertStringContainsString('scanner.aho_corasick', $text);
            $this->assertStringNotContainsString('broken', $text);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testMetricsExportWritesJsonFile(): void
    {
        $source = sys_get_temp_dir() . '/fireline-cli-metrics-' . uniqid('', true) . '.json';
        $destination = sys_get_temp_dir() . '/fireline-cli-metrics-export-' . uniqid('', true) . '.json';
        MetricsStore::write($source, RuleMetrics::snapshotFrom([
            'cache.safe.hit' => 2,
        ], []));

        try {
            $command = escapeshellarg(PHP_BINARY) . ' fire.php metrics:export ' . escapeshellarg($source) . ' ' . escapeshellarg($destination);
            exec($command, $output, $exitCode);

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Metrics exported:', implode("\n", $output));
            $decoded = json_decode((string) file_get_contents($destination), true);
            $this->assertSame(2, $decoded['counters']['cache.safe.hit']);
            $this->assertSame($source, $decoded['source_path']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $decoded['exported_at']);
        } finally {
            foreach ([$source, $destination] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    protected function replayEvent(string $route, string $field, string $value): array
    {
        return [
            'request' => [
                'route' => $route,
            ],
            'results' => [
                [
                    'field' => $field,
                    'source' => 'get',
                    'normalized' => $value,
                ],
            ],
            'decision' => [
                'blocked' => false,
                'score' => 0,
            ],
        ];
    }
}
