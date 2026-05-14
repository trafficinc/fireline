<?php

use PHPUnit\Framework\TestCase;

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

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('storage/replay/traffic.ndjson', implode("\n", $output));
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
}
