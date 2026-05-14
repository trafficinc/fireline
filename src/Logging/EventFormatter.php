<?php

namespace Fireline\Logging;

use Fireline\Engine\Decision;

class EventFormatter
{
    public static function blockedDecision(Decision $decision): array
    {
        $context = $decision->context();
        $request = $context ? $context->request() : [];
        $matched = $decision->matchedResult();

        return [
            'level' => 'warn',
            'event' => 'fireline.blocked_request',
            'timestamp' => date('c'),
            'unix_time' => time(),
            'remote_addr' => self::clean((string) ($request['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))),
            'method' => self::clean((string) ($request['method'] ?? get_request_method())),
            'route' => self::clean((string) ($request['route'] ?? '')),
            'request_uri' => self::clean((string) ($request['uri'] ?? ($_SERVER['REQUEST_URI'] ?? ''))),
            'filter' => self::clean((string) ($matched['source'] ?? $decision->reason())),
            'field' => self::clean((string) ($matched['field'] ?? '')),
            'score' => $decision->score(),
            'matched_score' => (int) ($matched['score'] ?? 0),
            'reason' => self::clean($decision->reason()),
            'explanation' => $decision->explanation(),
            'value' => self::redact(self::clean((string) ($matched['value'] ?? ''))),
            'normalized' => self::redact(self::clean((string) ($matched['normalized'] ?? ''))),
            'user_agent' => self::clean((string) ($request['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))),
            'referer' => self::clean((string) ($request['referer'] ?? ($_SERVER['HTTP_REFERER'] ?? ''))),
        ];
    }

    public static function clean(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/', ' ', $value);
    }

    public static function redact(string $value): string
    {
        $value = preg_replace(
            '/\b(password|passwd|pwd|token|api[_-]?key|secret|authorization)=([^&\s]+)/i',
            '$1=[redacted]',
            $value
        );

        if (strlen($value) > 1000) {
            $value = substr($value, 0, 1000) . '...';
        }

        return $value;
    }
}
