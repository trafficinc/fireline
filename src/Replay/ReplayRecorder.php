<?php

namespace Fireline\Replay;

use Fireline\Engine\Decision;

class ReplayRecorder
{
    protected $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function record(Decision $decision): bool
    {
        $context = $decision->context();
        if (!$context) {
            return false;
        }

        $event = [
            'version' => 1,
            'recorded_at' => date('c'),
            'request' => $this->request($context->request()),
            'results' => $this->results($decision->results()),
            'decision' => [
                'blocked' => $decision->shouldBlock(),
                'reason' => $decision->reason(),
                'score' => $decision->score(),
            ],
        ];

        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        return file_put_contents($this->path, json_encode($event) . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
    }

    protected function request(array $request): array
    {
        return [
            'method' => (string) ($request['method'] ?? ''),
            'route' => (string) ($request['route'] ?? ''),
            'uri' => (string) ($request['uri'] ?? ''),
            'ip' => (string) ($request['ip'] ?? ''),
            'user_agent' => (string) ($request['user_agent'] ?? ''),
        ];
    }

    protected function results(array $results): array
    {
        return array_values(array_map(function (array $result): array {
            return [
                'field' => (string) ($result['field'] ?? ''),
                'source' => (string) ($result['source'] ?? ''),
                'value' => (string) ($result['value'] ?? ''),
                'normalized' => (string) ($result['normalized'] ?? ''),
                'score' => (int) ($result['score'] ?? 0),
                'matched_rules' => array_values(array_map(function (array $match): string {
                    return (string) ($match['id'] ?? $match['pattern'] ?? 'unknown');
                }, is_array($result['matches'] ?? null) ? $result['matches'] : [])),
                'breakdown' => is_array($result['breakdown'] ?? null) ? $result['breakdown'] : [],
            ];
        }, $results));
    }
}
