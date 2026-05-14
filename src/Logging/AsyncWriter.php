<?php

namespace Fireline\Logging;

use Exception;

class AsyncWriter
{
    protected $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: dirname(__DIR__, 2) . '/storage/logs/fireline.log';
    }

    public function write(array $event): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new Exception('Cannot create log directory, please check permissions.');
        }

        $encoded = json_encode($event, JSON_UNESCAPED_SLASHES);
        if ($encoded === false || file_put_contents($this->path, $encoded . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new Exception('Cannot write to log file, please check permissions.');
        }
    }
}
