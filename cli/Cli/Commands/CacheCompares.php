<?php

namespace Cli\Commands;

use Cli\Dumper;
use DirectoryIterator;

class CacheCompares {

    public function go() {
        $directory = dirname(dirname(dirname(__DIR__))) . '/src/Compares';
        $dir = new DirectoryIterator($directory);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->getFilename() !== '.' && $fileinfo->getFilename() !== '..'){

                $array = explode("\n", file_get_contents($directory ."/".$fileinfo->getFilename()));
                $this->writeToFile($array, $fileinfo->getFilename());
            }
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

        $file = dirname(dirname(dirname(__DIR__))) . '/storage/cache/'.$matches[1].'.php';
        $res = file_put_contents($file,  $statement, LOCK_EX);
        if ($res > 0) {
            echo "$matches[1].php  Cached [ok]\n";
        }
    }

    public function clear(): string {
        $directory = dirname(dirname(dirname(__DIR__))) . '/storage/cache';
        $dir = new DirectoryIterator($directory);
        $checks = 0;
        foreach ($dir as $fileinfo) {
            if ($fileinfo->getFilename() !== '.' && $fileinfo->getFilename() !== '..'){
                if ( file_exists($directory . '/'.$fileinfo->getFilename()) ){
                    $checks += 1;
                    unlink($directory . '/'.$fileinfo->getFilename());
                }
            }
        }
        if ($checks > 0){
            return "Cache Deleted.";
        } else {
            return "No cache files, nothing to delete.";
        }
    }

    public function check() : bool {
        $directory = dirname(dirname(dirname(__DIR__))) . '/storage/cache';
        $dir = new DirectoryIterator($directory);
        $checks = 0;
        foreach ($dir as $fileinfo) {
            if ($fileinfo->getFilename() !== '.' && $fileinfo->getFilename() !== '..'){
                if ( file_exists($directory . '/'.$fileinfo->getFilename()) ){
                    $checks += 1;
                }
            }
        }
        if ($checks > 0) {
            return true;
        } else {
            return false;
        }
    }

}