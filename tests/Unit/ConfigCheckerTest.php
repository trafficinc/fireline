<?php

use Fireline\Config\ConfigChecker;
use PHPUnit\Framework\TestCase;

class ConfigCheckerTest extends TestCase
{
    public function testReportsValidConfiguration(): void
    {
        $result = (new ConfigChecker(dirname(__DIR__, 2)))->check([
            'paranoia_level' => 'high',
            'score_threshold' => 20,
            'regex_threshold' => null,
            'safe_cache_threshold' => null,
            'replay_path' => sys_get_temp_dir() . '/fireline-replay.ndjson',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('paranoia_level', $result['checks'][0]['name']);
        $this->assertSame('ok', $result['checks'][0]['status']);
    }

    public function testReportsInvalidParanoiaAndThresholds(): void
    {
        $result = (new ConfigChecker(dirname(__DIR__, 2)))->check([
            'paranoia_level' => 'maximum',
            'score_threshold' => 0,
            'replay_path' => sys_get_temp_dir() . '/fireline-replay.ndjson',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('error', $result['checks'][0]['status']);
        $this->assertSame('error', $result['checks'][1]['status']);
    }

    public function testAllowsMissingDirectoryWhenParentIsWritable(): void
    {
        $result = (new ConfigChecker(dirname(__DIR__, 2)))->check([
            'replay_path' => sys_get_temp_dir() . '/fireline-missing-' . uniqid('', true) . '/traffic.ndjson',
        ]);

        $replayCheck = array_values(array_filter($result['checks'], function (array $check): bool {
            return $check['name'] === 'replay_path';
        }))[0];

        $this->assertSame('ok', $replayCheck['status']);
        $this->assertStringContainsString('Will create under writable parent', $replayCheck['message']);
    }

    public function testReportsStorageDirectoryStatus(): void
    {
        $result = (new ConfigChecker(dirname(__DIR__, 2)))->check();

        $storageCheck = array_values(array_filter($result['checks'], function (array $check): bool {
            return $check['name'] === 'storage_dir';
        }))[0];

        $this->assertSame('ok', $storageCheck['status']);
    }
}
