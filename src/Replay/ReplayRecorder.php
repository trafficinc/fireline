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
            'method' => $this->clean((string) ($request['method'] ?? '')),
            'route' => $this->clean((string) ($request['route'] ?? '')),
            'uri' => $this->clean((string) ($request['uri'] ?? '')),
            'ip' => $this->clean((string) ($request['ip'] ?? '')),
            'user_agent' => $this->clean((string) ($request['user_agent'] ?? '')),
        ];
    }

    protected function results(array $results): array
    {
        return array_values(array_map(function (array $result): array {
            $field = (string) ($result['field'] ?? '');
            $sensitive = $this->isSensitiveField($field);

            return [
                'field' => $this->clean($field),
                'source' => $this->clean((string) ($result['source'] ?? '')),
                'value' => $sensitive ? '[redacted]' : $this->clean((string) ($result['value'] ?? '')),
                'normalized' => $sensitive ? '[redacted]' : $this->clean((string) ($result['normalized'] ?? '')),
                'score' => (int) ($result['score'] ?? 0),
                'matched_rules' => array_values(array_map(function (array $match): string {
                    return $this->clean((string) ($match['id'] ?? $match['pattern'] ?? 'unknown'));
                }, is_array($result['matches'] ?? null) ? $result['matches'] : [])),
                'breakdown' => is_array($result['breakdown'] ?? null) ? $result['breakdown'] : [],
                'redacted' => $sensitive,
            ];
        }, $results));
    }

    protected function isSensitiveField(string $field): bool
    {
        return preg_match('/(?:password|passwd|pwd|token|api[_\.-]?key|secret|authorization|auth)/i', $field) === 1;
    }

    protected function clean(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/', ' ', $value);
        $value = is_string($value) ? $value : '';

        return strlen($value) > 1000 ? substr($value, 0, 1000) . '...' : $value;
    }
}
