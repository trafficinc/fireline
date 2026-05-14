<?php

namespace Fireline\Replay;

use Fireline\Engine\WafEngine;

class ReplayRunner
{
    protected $engine;

    public function __construct(?WafEngine $engine = null)
    {
        $this->engine = $engine ?: new WafEngine(['replay_enabled' => false]);
    }

    public function replay(string $path): array
    {
        if (!is_readable($path)) {
            return [
                'total' => 0,
                'regressions' => [],
            ];
        }

        $total = 0;
        $invalid = 0;
        $regressions = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [
                'total' => 0,
                'regressions' => [],
            ];
        }

        while (($line = fgets($handle)) !== false) {
            $event = json_decode($line, true);
            if (!is_array($event)) {
                $invalid++;
                continue;
            }

            $total++;
            $regression = $this->compareEvent($event, $this->engine->inspectReplayEvent($event));
            if ($regression !== null) {
                $regressions[] = $regression;
            }
        }

        fclose($handle);

        return [
            'total' => $total,
            'invalid' => $invalid,
            'regressions' => $regressions,
            'summary' => $this->summary($total, $invalid, $regressions),
        ];
    }

    protected function compareEvent(array $event, $decision): ?array
    {
        $previous = $event['decision'] ?? [];
        $previousScore = (int) ($previous['score'] ?? 0);
        $previousBlocked = (bool) ($previous['blocked'] ?? false);
        $currentScore = $decision->score();
        $currentBlocked = $decision->shouldBlock();

        if (!$previousBlocked && $currentBlocked) {
            return [
                'type' => 'new_block',
                'route' => (string) ($event['request']['route'] ?? ''),
                'previous_score' => $previousScore,
                'current_score' => $currentScore,
                'previous_blocked' => false,
                'current_blocked' => true,
                'explanation' => $decision->explanation(),
            ];
        }

        if ($previousBlocked && !$currentBlocked) {
            return [
                'type' => 'missed_block',
                'route' => (string) ($event['request']['route'] ?? ''),
                'previous_score' => $previousScore,
                'current_score' => $currentScore,
                'previous_blocked' => true,
                'current_blocked' => false,
                'previous_reason' => (string) ($previous['reason'] ?? ''),
                'explanation' => $decision->explanation(),
            ];
        }

        if ($currentScore > $previousScore) {
            return [
                'type' => 'score_increase',
                'route' => (string) ($event['request']['route'] ?? ''),
                'previous_score' => $previousScore,
                'current_score' => $currentScore,
                'previous_blocked' => $previousBlocked,
                'current_blocked' => $currentBlocked,
            ];
        }

        return null;
    }

    protected function summary(int $total, int $invalid, array $regressions): array
    {
        $byType = [
            'new_block' => 0,
            'missed_block' => 0,
            'score_increase' => 0,
        ];

        $byRoute = [];
        foreach ($regressions as $regression) {
            $type = (string) ($regression['type'] ?? 'unknown');
            $route = (string) ($regression['route'] ?? '');
            $byType[$type] = ($byType[$type] ?? 0) + 1;

            if ($route !== '') {
                $byRoute[$route] = ($byRoute[$route] ?? 0) + 1;
            }
        }

        arsort($byRoute);

        return [
            'total' => $total,
            'invalid' => $invalid,
            'regressions' => count($regressions),
            'by_type' => $byType,
            'by_route' => $byRoute,
        ];
    }
}
