#!/usr/bin/php
<?php

/*
use CLI to cache config files for speed and update rules, etc.
*/

if (php_sapi_name() !== 'cli') {
    exit;
}
require "cliautoload.php";
require "./cli/Cli/clihelpers.php";

use Cli\Commands\CacheCompares;

use Cli\Cli;

$cli = new Cli();

//$cli->registerCommand('cache:data', function (array $argv) use ($cli) {
//    (new CacheCompares)->go();
//});

//$cli->registerCommand('cache:clear', function (array $argv) use ($cli) {
//    $cached = (new CacheCompares)->clear();
//    $cli->getPrinter()->display($cached);
//});

//$cli->registerCommand('cache:check', function (array $argv) use ($cli) {
//    $checked = (new CacheCompares)->check();
//    if ($checked) {
//        $cli->getPrinter()->display("Files are cached.");
//    } else {
//        $cli->getPrinter()->display("Files are not cached.");
//    }
//});

$cli->registerCommand('help', function (array $argv) use ($cli) {
    $menu = "+--------------+-------------------------------------------+
|  usage: ./fireline.php [options]                         |
+--------------+-------------------------------------------+
|  cache:data  |  Will cache your .txt definition files.   |
+--------------+-------------------------------------------+
|  cache:check |  Will check if your files are cached.     |
+--------------+-------------------------------------------+
|  cache:clear |  Will delete the cached files.            |
+--------------+-------------------------------------------+";
    $cli->getPrinter()->display( $menu );
});

$cli->runCommand($argv);