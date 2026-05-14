<?php

use Cli\Commands\CacheCompares;
use PHPUnit\Framework\TestCase;

class CacheComparesTest extends TestCase
{
    private $root;
    private $compares;
    private $cache;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/fireline-cache-compares-' . uniqid('', true);
        $this->compares = $this->root . '/compares';
        $this->cache = $this->root . '/cache';

        mkdir($this->compares, 0775, true);
        mkdir($this->cache, 0775, true);

        file_put_contents($this->compares . '/bots.php', "bot-one\nbot-two");
        file_put_contents($this->compares . '/ips.php', "203.0.113.10");
        file_put_contents($this->compares . '/ips_white_list.php', "192.0.2.0/24");
        file_put_contents($this->compares . '/ip_block_by_country.php', "ZZ");
        file_put_contents($this->compares . '/sql.php', "legacy-sql");
        file_put_contents($this->compares . '/xss.php', "legacy-xss");
        file_put_contents($this->compares . '/query.php', "legacy-query");
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testCachesOnlyActiveGuardCompareFiles(): void
    {
        $command = new CacheCompares($this->compares, $this->cache);

        ob_start();
        $command->go();
        ob_end_clean();

        $this->assertFileExists($this->cache . '/bots.php');
        $this->assertFileExists($this->cache . '/ips.php');
        $this->assertFileExists($this->cache . '/ips_white_list.php');
        $this->assertFileExists($this->cache . '/ip_block_by_country.php');
        $this->assertFileDoesNotExist($this->cache . '/sql.php');
        $this->assertFileDoesNotExist($this->cache . '/xss.php');
        $this->assertFileDoesNotExist($this->cache . '/query.php');
        $this->assertSame(['bot-one', 'bot-two'], require $this->cache . '/bots.php');
    }

    public function testClearOnlyRemovesActiveGuardCacheFiles(): void
    {
        file_put_contents($this->cache . '/bots.php', '<?php return [];');
        file_put_contents($this->cache . '/sql.php', '<?php return [];');

        $command = new CacheCompares($this->compares, $this->cache);

        $this->assertSame('Cache Deleted.', $command->clear());
        $this->assertFileDoesNotExist($this->cache . '/bots.php');
        $this->assertFileExists($this->cache . '/sql.php');
    }

    public function testCheckOnlyReportsActiveGuardCacheFiles(): void
    {
        $command = new CacheCompares($this->compares, $this->cache);

        $this->assertFalse($command->check());

        file_put_contents($this->cache . '/sql.php', '<?php return [];');
        $this->assertFalse($command->check());

        file_put_contents($this->cache . '/ips.php', '<?php return [];');
        $this->assertTrue($command->check());
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = array_diff(scandir($path), ['.', '..']);
        foreach ($items as $item) {
            $target = $path . '/' . $item;
            if (is_dir($target)) {
                $this->removeTree($target);
                continue;
            }

            unlink($target);
        }

        rmdir($path);
    }
}
