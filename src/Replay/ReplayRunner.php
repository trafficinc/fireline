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
            return $this->emptyResult();
        }

        $total = 0;
        $invalid = 0;
        $regressions = [];
        $stats = $this->emptyStats();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return $this->emptyResult();
        }

        while (($line = fgets($handle)) !== false) {
            $event = json_decode($line, true);
            if (!is_array($event)) {
                $invalid++;
                continue;
            }

            $total++;
            $decision = $this->engine->inspectReplayEvent($event);
            $this->recordStats($stats, $event, $decision);
            $regression = $this->compareEvent($event, $decision);
            if ($regression !== null) {
                $regressions[] = $regression;
            }
        }

        fclose($handle);

        return [
            'total' => $total,
            'invalid' => $invalid,
            'regressions' => $regressions,
            'summary' => $this->summary($total, $invalid, $regressions, $stats),
            'metadata' => [
                'current' => $this->engine->replayMetadata(),
            ],
        ];
    }

    protected function emptyResult(): array
    {
        return [
            'total' => 0,
            'invalid' => 0,
            'regressions' => [],
            'summary' => $this->summary(0, 0, [], $this->emptyStats()),
            'metadata' => [
                'current' => $this->engine->replayMetadata(),
            ],
        ];
    }

    protected function compareEvent(array $event, $decision): ?array
    {
        $previous = $event['decision'] ?? [];
        $previousScore = (int) ($previous['score'] ?? 0);
        $previousBlocked = (bool) ($previous['blocked'] ?? false);
        $currentScore = $decision->score();
        $currentBlocked = $decision->shouldBlock();
        $metadataChanged = $this->metadataChanged($event);
        $metadataDiff = $metadataChanged ? $this->metadataDiff($event) : [];

        if (!$previousBlocked && $currentBlocked) {
            return [
                'type' => 'new_block',
                'route' => (string) ($event['request']['route'] ?? ''),
                'previous_score' => $previousScore,
                'current_score' => $currentScore,
                'previous_blocked' => false,
                'current_blocked' => true,
                'metadata_changed' => $metadataChanged,
                'metadata_diff' => $metadataDiff,
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
                'metadata_changed' => $metadataChanged,
                'metadata_diff' => $metadataDiff,
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
                'metadata_changed' => $metadataChanged,
                'metadata_diff' => $metadataDiff,
            ];
        }

        return null;
    }

    protected function metadataChanged(array $event): bool
    {
        $previous = $event['metadata'] ?? null;
        if (!is_array($previous)) {
            return false;
        }

        return $previous != $this->engine->replayMetadata();
    }

    protected function metadataDiff(array $event): array
    {
        $previous = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $current = $this->engine->replayMetadata();
        $keys = array_unique(array_merge(array_keys($previous), array_keys($current)));
        $changed = [];

        foreach ($keys as $key) {
            if (($previous[$key] ?? null) != ($current[$key] ?? null)) {
                $changed[] = (string) $key;
            }
        }

        return [
            'changed' => $changed,
        ];
    }

    protected function emptyStats(): array
    {
        return [
            'previous_blocked' => 0,
            'current_blocked' => 0,
            'allowed_to_blocked' => 0,
            'blocked_to_allowed' => 0,
            'unchanged_decision' => 0,
            'score_increased' => 0,
            'score_decreased' => 0,
            'score_unchanged' => 0,
            'total_score_delta' => 0,
            'max_score_increase' => 0,
            'max_score_decrease' => 0,
        ];
    }

    protected function recordStats(array &$stats, array $event, $decision): void
    {
        $previous = $event['decision'] ?? [];
        $previousScore = (int) ($previous['score'] ?? 0);
        $previousBlocked = (bool) ($previous['blocked'] ?? false);
        $currentScore = $decision->score();
        $currentBlocked = $decision->shouldBlock();
        $delta = $currentScore - $previousScore;

        $stats['previous_blocked'] += $previousBlocked ? 1 : 0;
        $stats['current_blocked'] += $currentBlocked ? 1 : 0;
        $stats['allowed_to_blocked'] += (!$previousBlocked && $currentBlocked) ? 1 : 0;
        $stats['blocked_to_allowed'] += ($previousBlocked && !$currentBlocked) ? 1 : 0;
        $stats['unchanged_decision'] += ($previousBlocked === $currentBlocked) ? 1 : 0;
        $stats['score_increased'] += $delta > 0 ? 1 : 0;
        $stats['score_decreased'] += $delta < 0 ? 1 : 0;
        $stats['score_unchanged'] += $delta === 0 ? 1 : 0;
        $stats['total_score_delta'] += $delta;
        $stats['max_score_increase'] = max($stats['max_score_increase'], $delta);
        $stats['max_score_decrease'] = min($stats['max_score_decrease'], $delta);
    }

    protected function summary(int $total, int $invalid, array $regressions, array $stats): array
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
            'decision_changes' => [
                'previous_blocked' => $stats['previous_blocked'],
                'current_blocked' => $stats['current_blocked'],
                'allowed_to_blocked' => $stats['allowed_to_blocked'],
                'blocked_to_allowed' => $stats['blocked_to_allowed'],
                'unchanged' => $stats['unchanged_decision'],
            ],
            'score_deltas' => [
                'increased' => $stats['score_increased'],
                'decreased' => $stats['score_decreased'],
                'unchanged' => $stats['score_unchanged'],
                'total_delta' => $stats['total_score_delta'],
                'average_delta' => $total > 0 ? $stats['total_score_delta'] / $total : 0,
                'max_increase' => $stats['max_score_increase'],
                'max_decrease' => $stats['max_score_decrease'],
            ],
        ];
    }
}
