#!/usr/bin/php
<?php

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
use Fireline\Telemetry\MetricsFormatter;
use Fireline\Telemetry\RuleMetrics;
use Fireline\Telemetry\MetricsStore;

$cli = new Cli();

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
|  baseline:export | Write route model candidates to a file.  |
+--------------+-------------------------------------------+
|  config:check | Validate Fireline config and writable paths. |
+--------------+-------------------------------------------+
|  metrics:show | Show in-process metrics snapshot.          |
+--------------+-------------------------------------------+
|  metrics:export | Export persisted metrics JSON.           |
+--------------+-------------------------------------------+
|  metrics:reset | Reset persisted metrics snapshot.         |
+--------------+-------------------------------------------+
|  examples    |  php fire.php replay:run storage/replay/traffic.ndjson |
|              |  php fire.php replay:run storage/replay/traffic.ndjson --json |
|              |  php fire.php baseline:build storage/replay/traffic.ndjson 10 --json |
|              |  php fire.php baseline:build storage/replay/traffic.ndjson 10 --json --report |
|              |  php fire.php baseline:export storage/replay/traffic.ndjson 10 storage/models/routes.generated.php |
|              |  php fire.php baseline:export storage/replay/traffic.ndjson 10 storage/models/routes.generated.php --dry-run |
|              |  php fire.php metrics:show storage/metrics/fireline-metrics.json --summary |
|              |  php fire.php metrics:export storage/metrics/fireline-metrics.json storage/metrics/export.json |
+--------------+-------------------------------------------+";
    $cli->getPrinter()->display( $menu );
});

$cli->registerCommand('replay:run', function (array $argv) use ($cli) {
    $ciMode = CommandArgs::hasFlag($argv, '--ci');
    $jsonMode = CommandArgs::hasFlag($argv, '--json');
    $path = CommandArgs::firstValue($argv, 2, __DIR__ . '/storage/replay/traffic.ndjson');

    $result = (new ReplayRunner())->replay($path);

    if ($jsonMode) {
        $encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $cli->getPrinter()->display(is_string($encoded) ? $encoded : '{}');

        if ($ciMode && count($result['regressions']) > 0) {
            exit(1);
        }

        return;
    }

    $lines = [
        'Replay file: ' . $path,
        'Events replayed: ' . $result['total'],
        'Invalid lines: ' . ($result['invalid'] ?? 0),
        'Regressions: ' . count($result['regressions']),
    ];

    if (isset($result['summary']['by_type']) && is_array($result['summary']['by_type']) && array_sum($result['summary']['by_type']) > 0) {
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
        $lines[] = 'Metadata Changed: ' . (!empty($regression['metadata_changed']) ? 'yes' : 'no');
        if (isset($regression['metadata_diff']['changed']) && is_array($regression['metadata_diff']['changed']) && $regression['metadata_diff']['changed'] !== []) {
            $lines[] = 'Metadata Diff: ' . implode(', ', $regression['metadata_diff']['changed']);
        }

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
    $report = BaselineBuilder::buildReportFromReplayFile($path, $minSamples);
    $model = $report['model'];

    if (CommandArgs::hasFlag($argv, '--json')) {
        $payload = CommandArgs::hasFlag($argv, '--report') ? $report : $model;
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $cli->getPrinter()->display(is_string($encoded) ? $encoded : '{}');
        return;
    }

    $cli->getPrinter()->display(
        "Replay file: " . $path . PHP_EOL .
        "Events read: " . $report['total'] . PHP_EOL .
        "Invalid lines: " . $report['invalid'] . PHP_EOL .
        "Minimum samples: " . $minSamples . PHP_EOL .
        "Route model:" . PHP_EOL .
        RouteModelExporter::toPhp($model)
    );
});

$cli->registerCommand('baseline:export', function (array $argv) use ($cli) {
    $values = CommandArgs::values($argv, 2);
    $path = $values[0] ?? __DIR__ . '/storage/replay/traffic.ndjson';
    $minSamples = max(1, (int) ($values[1] ?? 3));
    $destination = $values[2] ?? __DIR__ . '/storage/models/routes.generated.php';
    $report = BaselineBuilder::buildReportFromReplayFile($path, $minSamples);
    $dir = dirname($destination);
    $lines = [
        (CommandArgs::hasFlag($argv, '--dry-run') ? 'Route model export preview: ' : 'Route model exported: ') . $destination,
        'Replay file: ' . $path,
        'Events read: ' . $report['total'],
        'Invalid lines: ' . $report['invalid'],
        'Minimum samples: ' . $minSamples,
    ];

    if (CommandArgs::hasFlag($argv, '--dry-run')) {
        $cli->getPrinter()->display(implode(PHP_EOL, $lines));
        return;
    }

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        $cli->getPrinter()->display('Unable to create route model directory: ' . $dir);
        exit(1);
    }

    if (file_put_contents($destination, RouteModelExporter::toPhp($report['model']), LOCK_EX) === false) {
        $cli->getPrinter()->display('Unable to export route model: ' . $destination);
        exit(1);
    }

    $cli->getPrinter()->display(implode(PHP_EOL, $lines));
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

$cli->registerCommand('metrics:show', function (array $argv) use ($cli) {
    $path = CommandArgs::firstValue($argv, 2, __DIR__ . '/storage/metrics/fireline-metrics.json');
    $snapshot = CommandArgs::hasFlag($argv, '--live') || !is_readable($path)
        ? RuleMetrics::snapshot()
        : MetricsStore::read($path);

    if (CommandArgs::hasFlag($argv, '--json')) {
        $output = MetricsFormatter::json($snapshot);
    } elseif (CommandArgs::hasFlag($argv, '--summary')) {
        $output = MetricsFormatter::summary($snapshot);
    } else {
        $output = MetricsFormatter::text($snapshot);
    }

    $cli->getPrinter()->display($output);
});

$cli->registerCommand('metrics:export', function (array $argv) use ($cli) {
    $values = CommandArgs::values($argv, 2);
    $source = $values[0] ?? __DIR__ . '/storage/metrics/fireline-metrics.json';
    $destination = $values[1] ?? __DIR__ . '/storage/metrics/fireline-metrics-export.json';
    $snapshot = CommandArgs::hasFlag($argv, '--live') || !is_readable($source)
        ? RuleMetrics::snapshot()
        : MetricsStore::read($source);
    $snapshot['exported_at'] = date('c');
    $snapshot['source_path'] = $source;
    $dir = dirname($destination);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        $cli->getPrinter()->display('Unable to create metrics export directory: ' . $dir);
        exit(1);
    }

    if (file_put_contents($destination, MetricsFormatter::json($snapshot) . PHP_EOL, LOCK_EX) === false) {
        $cli->getPrinter()->display('Unable to export metrics: ' . $destination);
        exit(1);
    }

    $cli->getPrinter()->display('Metrics exported: ' . $destination);
});

$cli->registerCommand('metrics:reset', function (array $argv) use ($cli) {
    $path = CommandArgs::firstValue($argv, 2, __DIR__ . '/storage/metrics/fireline-metrics.json');

    if (CommandArgs::hasFlag($argv, '--live')) {
        RuleMetrics::reset();
    }

    if (!MetricsStore::reset($path)) {
        $cli->getPrinter()->display('Unable to reset metrics file: ' . $path);
        exit(1);
    }

    $cli->getPrinter()->display('Metrics reset: ' . $path);
});

$cli->runCommand($argv);
