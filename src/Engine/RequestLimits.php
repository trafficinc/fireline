<?php

namespace Fireline\Engine;

use Fireline\Extract\RequestField;
use Fireline\Telemetry\RuleMetrics;

class RequestLimits
{
    protected $config;
    protected $threshold;

    public function __construct(array $config, int $threshold)
    {
        $this->config = $config;
        $this->threshold = $threshold;
    }

    public function inspect(array $request): ?ScanResult
    {
        $started = microtime(true);
        RuleMetrics::increment('request_limits.evaluated');
        $violations = [];

        $this->checkCount($violations, 'max_fields', count((array) ($request['fields'] ?? [])), (int) $this->config['max_fields']);
        $this->checkCount($violations, 'max_headers', count((array) ($request['headers'] ?? [])), (int) $this->config['max_headers']);
        $this->checkCount($violations, 'max_body_length', (int) ($request['raw_body_length'] ?? strlen((string) ($request['body'] ?? ''))), (int) $this->config['max_body_length']);

        foreach ((array) ($request['headers'] ?? []) as $name => $value) {
            $value = (string) $value;
            $this->checkCount($violations, 'max_header_length', strlen($value), (int) $this->config['max_header_length'], 'header.' . (string) $name);
            $this->checkEncoding($violations, $value, 'header.' . (string) $name);
        }

        foreach ((array) ($request['fields'] ?? []) as $field) {
            if (!$field instanceof RequestField) {
                continue;
            }

            $this->checkEncoding($violations, $field->value(), $field->name());
        }

        $this->checkEncoding($violations, (string) ($request['query_string'] ?? ''), 'query_string');
        $this->checkEncoding($violations, (string) ($request['body'] ?? ''), 'raw.body');

        if ($violations === []) {
            RuleMetrics::timing('request_limits.inspect', (microtime(true) - $started) * 1000);
            return null;
        }

        $breakdown = [];
        foreach ($violations as $violation) {
            $breakdown[$violation['id']] = $this->threshold;
            RuleMetrics::increment('request_limits.' . strtolower((string) $violation['id']));
        }
        RuleMetrics::timing('request_limits.inspect', (microtime(true) - $started) * 1000);

        return new ScanResult(
            'request',
            'limit',
            $this->threshold,
            $violations,
            $breakdown,
            '',
            '',
            ''
        );
    }

    protected function checkCount(array &$violations, string $id, int $actual, int $limit, string $field = 'request'): void
    {
        if ($limit > 0 && $actual > $limit) {
            $violations[] = [
                'id' => 'REQUEST_LIMIT_' . strtoupper($id),
                'field' => $field,
                'actual' => $actual,
                'limit' => $limit,
            ];
        }
    }

    protected function checkEncoding(array &$violations, string $value, string $field): void
    {
        if ($value === '') {
            return;
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 1) {
            $violations[] = [
                'id' => 'REQUEST_LIMIT_MALFORMED_URL_ENCODING',
                'field' => $field,
            ];
        }

        if (preg_match('//u', $value) !== 1) {
            $violations[] = [
                'id' => 'REQUEST_LIMIT_INVALID_UTF8',
                'field' => $field,
            ];
        }
    }
}
