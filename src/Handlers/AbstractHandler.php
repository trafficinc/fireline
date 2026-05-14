<?php

namespace Handlers;

use Fireline\Engine\FieldInspector;
use Fireline\Extract\RequestField;
use Fireline\Normalize\Normalizer;
use Fireline\Scoring\Thresholds;

abstract class AbstractHandler implements Handler
{
    /**
     * @var Handler
     */
    private $nextHandler;

    public function setNext(Handler $handler): Handler
    {
        $this->nextHandler = $handler;
        return $handler;
    }

    public function handle(string $filter, array $request): ?string
    {
        return $this->forward($filter, $request);
    }

    protected function forward(string $filter, array $request): ?string
    {
        if ($this->nextHandler) {
            return $this->nextHandler->handle($filter, $request);
        }

        return null;
    }

    protected function blockOrForward(?string $badValue, string $filter, array $request): ?string
    {
        if ($badValue !== null) {
            $this->handleService($badValue, $filter, $request['request_method'] ?? '');
        }

        return $this->forward($filter, $request);
    }

    protected function firstUnsafeValue(array $request, array $categories = []): ?string
    {
        return $this->firstBlockedValue($request, [
            'get_request_method' => 'get',
            'post_request_method' => 'post',
        ], $categories);
    }

    protected function firstBlockedValue(array $request, array $requestKeys, array $categories = []): ?string
    {
        $configs = is_array($request['configs'] ?? null) ? $request['configs'] : [];
        $thresholds = new Thresholds($configs);
        $inspector = new FieldInspector($thresholds);
        $normalizer = new Normalizer();
        $engineRequest = [
            'method' => (string) ($request['request_method'] ?? ''),
            'route' => (string) ($request['route'] ?? ''),
        ];

        foreach ($requestKeys as $requestKey => $source) {
            foreach ((array) ($request[$requestKey] ?? []) as $name => $value) {
                $field = new RequestField((string) $name, (string) $value, (string) $source);
                $result = $inspector->inspect($engineRequest, $field, $normalizer->run($field->value()), false);

                if ($result !== null && $result->score() >= $thresholds->blockThreshold() && $this->matchesAllowedCategory($result->toArray(), $categories)) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    protected function matchesAllowedCategory(array $result, array $categories): bool
    {
        if ($categories === []) {
            return true;
        }

        $allowed = array_flip(array_map('strtolower', $categories));
        foreach ((array) ($result['matches'] ?? []) as $match) {
            $category = strtolower((string) ($match['category'] ?? ''));
            if (isset($allowed[$category])) {
                return true;
            }
        }

        return false;
    }
}
