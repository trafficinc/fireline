#!/usr/bin/php
<?php

/*
use CLI to cache config files for speed and update rules, etc.
*/

if (php_sapi_name() !== 'cli') {
    exit;
}
require "cliautoload.php";
require "autoload.php";
require "./cli/Cli/clihelpers.php";

use Cli\Cli;
use Fireline\Learning\BaselineBuilder;
use Fireline\Replay\ReplayRunner;

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
|  usage: php fire.php [command]                           |
+--------------+-------------------------------------------+
|  help        |  Show this menu.                          |
+--------------+-------------------------------------------+
|  replay:run  |  Replay stored traffic and show changes.  |
+--------------+-------------------------------------------+
|  baseline:build | Build route model candidates from replay. |
+--------------+-------------------------------------------+
|  example     |  php fire.php replay:run storage/replay/traffic.ndjson |
+--------------+-------------------------------------------+";
    $cli->getPrinter()->display( $menu );
});

$cli->registerCommand('replay:run', function (array $argv) use ($cli) {
    $path = $argv[2] ?? __DIR__ . '/storage/replay/traffic.ndjson';
    $result = (new ReplayRunner())->replay($path);

    $lines = [
        'Replay file: ' . $path,
        'Events replayed: ' . $result['total'],
        'Regressions: ' . count($result['regressions']),
    ];

    foreach ($result['regressions'] as $index => $regression) {
        $lines[] = '';
        $lines[] = '#' . ($index + 1) . ' ' . ($regression['type'] ?? 'regression');
        $lines[] = 'Route: ' . ($regression['route'] ?? '');
        $lines[] = 'Previous Score: ' . ($regression['previous_score'] ?? 0);
        $lines[] = 'Current Score: ' . ($regression['current_score'] ?? 0);

        if (isset($regression['explanation']) && is_array($regression['explanation'])) {
            $lines[] = 'Reason: ' . ($regression['explanation']['reason'] ?? '');
        }
    }

    $cli->getPrinter()->display(implode(PHP_EOL, $lines));
});

$cli->registerCommand('baseline:build', function (array $argv) use ($cli) {
    $path = $argv[2] ?? __DIR__ . '/storage/replay/traffic.ndjson';
    $minSamples = isset($argv[3]) ? max(1, (int) $argv[3]) : 3;
    $model = BaselineBuilder::buildFromReplayFile($path, $minSamples);

    $cli->getPrinter()->display(
        "Replay file: " . $path . PHP_EOL .
        "Minimum samples: " . $minSamples . PHP_EOL .
        "Route model:" . PHP_EOL .
        var_export($model, true)
    );
});

$cli->runCommand($argv);
