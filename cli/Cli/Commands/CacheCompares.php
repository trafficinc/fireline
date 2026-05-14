<?php

namespace Cli\Commands;

use Cli\Dumper;
use DirectoryIterator;

class CacheCompares {
    private const ACTIVE_COMPARE_FILES = [
        'bots.php',
        'ips.php',
        'ips_white_list.php',
        'ip_block_by_country.php',
    ];

    private $comparesDirectory;
    private $cacheDirectory;

    public function __construct(?string $comparesDirectory = null, ?string $cacheDirectory = null)
    {
        $root = dirname(dirname(dirname(__DIR__)));
        $this->comparesDirectory = $comparesDirectory ?? $root . '/src/Compares';
        $this->cacheDirectory = $cacheDirectory ?? $root . '/storage/cache';
    }

    public function go() {
        $directory = $this->comparesDirectory;
        $dir = new DirectoryIterator($directory);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isFile() || !in_array($fileinfo->getFilename(), self::ACTIVE_COMPARE_FILES, true)) {
                continue;
            }

            $array = explode("\n", file_get_contents($directory ."/".$fileinfo->getFilename()));
            $this->writeToFile($array, $fileinfo->getFilename());
        }

    }

    protected function writeToFile(array $array, string $fileName) {
        $dumper = new Dumper();
        //echo  $fileName;

        $statement = "<?php \n\n";
        $statement .= "return ";
        $statement .= $dumper->dump($array);
        $statement .= ";\n";

        preg_match('/(.*)\.[^.]+$/', $fileName, $matches);

        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0775, true);
        }

        $file = $this->cacheDirectory.'/'.$matches[1].'.php';
        $res = file_put_contents($file,  $statement, LOCK_EX);
        if ($res > 0) {
            echo "$matches[1].php  Cached [ok]\n";
        }
    }

    public function clear(): string {
        $directory = $this->cacheDirectory;
        if (!is_dir($directory)) {
            return "No cache files, nothing to delete.";
        }

        $dir = new DirectoryIterator($directory);
        $checks = 0;
        foreach ($dir as $fileinfo) {
            if ($this->isActiveCacheFile($fileinfo->getFilename()) && file_exists($directory . '/'.$fileinfo->getFilename())){
                $checks += 1;
                unlink($directory . '/'.$fileinfo->getFilename());
            }
        }
        if ($checks > 0){
            return "Cache Deleted.";
        } else {
            return "No cache files, nothing to delete.";
        }
    }

    public function check() : bool {
        $directory = $this->cacheDirectory;
        if (!is_dir($directory)) {
            return false;
        }

        $dir = new DirectoryIterator($directory);
        $checks = 0;
        foreach ($dir as $fileinfo) {
            if ($this->isActiveCacheFile($fileinfo->getFilename()) && file_exists($directory . '/'.$fileinfo->getFilename())){
                $checks += 1;
            }
        }
        if ($checks > 0) {
            return true;
        } else {
            return false;
        }
    }

    private function isActiveCacheFile(string $filename): bool
    {
        return in_array($filename, self::ACTIVE_COMPARE_FILES, true);
    }

}
