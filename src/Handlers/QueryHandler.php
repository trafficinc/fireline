<?php

namespace Handlers;

use LogService;

class QueryHandler extends AbstractHandler
{
    use LogService;

    public function handle(string $filter, array $request): ?string
    {
        if ($filter === 'queryString') {
            $badValue = $this->firstBlockedValue([
                'request_method' => $request['request_method'] ?? '',
                'route' => $request['route'] ?? '',
                'configs' => $request['configs'] ?? [],
                'query' => [
                    'query_string' => $request['query_string'] ?? '',
                ],
            ], [
                'query' => 'query',
            ]);

            return $this->blockOrForward($badValue === null ? null : (string) ($request['query_string'] ?? ''), $filter, $request);
        }

        return parent::handle($filter, $request);
    }
}
