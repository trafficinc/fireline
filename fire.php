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
use Cli\CommandArgs;
use Fireline\Config\ConfigChecker;
use Fireline\Learning\BaselineBuilder;
use Fireline\Learning\RouteModelExporter;
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
|  config:check | Validate Fireline config and writable paths. |
+--------------+-------------------------------------------+
|  example     |  php fire.php replay:run storage/replay/traffic.ndjson |
+--------------+-------------------------------------------+";
    $cli->getPrinter()->display( $menu );
});

$cli->registerCommand('replay:run', function (array $argv) use ($cli) {
    $ciMode = CommandArgs::hasFlag($argv, '--ci');
    $path = CommandArgs::firstValue($argv, 2, __DIR__ . '/storage/replay/traffic.ndjson');

    $result = (new ReplayRunner())->replay($path);

    $lines = [
        'Replay file: ' . $path,
        'Events replayed: ' . $result['total'],
        'Invalid lines: ' . ($result['invalid'] ?? 0),
        'Regressions: ' . count($result['regressions']),
    ];

    if (isset($result['summary']['by_type']) && is_array($result['summary']['by_type'])) {
        $lines[] = 'By type:';
        foreach ($result['summary']['by_type'] as $type => $count) {
            if ($count > 0) {
                $lines[] = '- ' . $type . ': ' . $count;
            }
        }
    }

    if (isset($result['summary']['by_route']) && is_array($result['summary']['by_route']) && $result['summary']['by_route'] !== []) {
        $lines[] = 'By route:';
        foreach (array_slice($result['summary']['by_route'], 0, 10, true) as $route => $count) {
            $lines[] = '- ' . $route . ': ' . $count;
        }
    }

    foreach ($result['regressions'] as $index => $regression) {
        $lines[] = '';
        $lines[] = '#' . ($index + 1) . ' ' . ($regression['type'] ?? 'regression');
        $lines[] = 'Route: ' . ($regression['route'] ?? '');
        $lines[] = 'Previous Score: ' . ($regression['previous_score'] ?? 0);
        $lines[] = 'Current Score: ' . ($regression['current_score'] ?? 0);
        $lines[] = 'Previous Blocked: ' . (!empty($regression['previous_blocked']) ? 'yes' : 'no');
        $lines[] = 'Current Blocked: ' . (!empty($regression['current_blocked']) ? 'yes' : 'no');

        if (isset($regression['explanation']) && is_array($regression['explanation'])) {
            $lines[] = 'Reason: ' . ($regression['explanation']['reason'] ?? '');
        }
    }

    $cli->getPrinter()->display(implode(PHP_EOL, $lines));

    if ($ciMode && count($result['regressions']) > 0) {
        exit(1);
    }
});

$cli->registerCommand('baseline:build', function (array $argv) use ($cli) {
    $path = CommandArgs::firstValue($argv, 2, __DIR__ . '/storage/replay/traffic.ndjson');
    $minSamples = CommandArgs::intValue($argv, 3, 3);
    $model = BaselineBuilder::buildFromReplayFile($path, $minSamples);

    $cli->getPrinter()->display(
        "Replay file: " . $path . PHP_EOL .
        "Minimum samples: " . $minSamples . PHP_EOL .
        "Route model:" . PHP_EOL .
        RouteModelExporter::toPhp($model)
    );
});

$cli->registerCommand('config:check', function (array $argv) use ($cli) {
    $result = (new ConfigChecker(__DIR__))->check();
    $lines = [
        'Config status: ' . ($result['ok'] ? 'ok' : 'error'),
    ];

    foreach ($result['checks'] as $check) {
        $lines[] = '[' . strtoupper($check['status']) . '] ' . $check['name'] . ': ' . $check['message'];
    }

    $cli->getPrinter()->display(implode(PHP_EOL, $lines));

    if (!$result['ok']) {
        exit(1);
    }
});

$cli->runCommand($argv);
