<?php

use Cli\CommandArgs;
use PHPUnit\Framework\TestCase;

class CommandArgsTest extends TestCase
{
    public function testFindsFlags(): void
    {
        $this->assertTrue(CommandArgs::hasFlag(['fire.php', 'replay:run', '--ci'], '--ci'));
        $this->assertFalse(CommandArgs::hasFlag(['fire.php', 'replay:run'], '--ci'));
    }

    public function testReturnsFirstNonFlagValue(): void
    {
        $argv = ['fire.php', 'replay:run', '--ci', 'storage/replay/traffic.ndjson'];

        $this->assertSame('storage/replay/traffic.ndjson', CommandArgs::firstValue($argv, 2, 'default'));
    }

    public function testReturnsAllNonFlagValues(): void
    {
        $argv = ['fire.php', 'metrics:export', '--json', 'metrics.json', '--force', 'export.json'];

        $this->assertSame(['metrics.json', 'export.json'], CommandArgs::values($argv, 2));
    }

    public function testIntValueHonorsMinimum(): void
    {
        $argv = ['fire.php', 'baseline:build', 'traffic.ndjson', '0'];

        $this->assertSame(1, CommandArgs::intValue($argv, 3, 3));
    }
}
