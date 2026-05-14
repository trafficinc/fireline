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
            'regressions' => $regressions,
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
                'explanation' => $decision->explanation(),
            ];
        }

        if ($currentScore > $previousScore) {
            return [
                'type' => 'score_increase',
                'route' => (string) ($event['request']['route'] ?? ''),
                'previous_score' => $previousScore,
                'current_score' => $currentScore,
                'blocked' => $currentBlocked,
            ];
        }

        return null;
    }
}
