<?php

namespace Handlers;

use Filters\Query;
use LogService;

class QueryHandler extends AbstractHandler
{
    use LogService;

    public function handle(string $filter, array $request): ?string
    {
        if ($filter === 'queryString') {
            $queryFilter = new Query();

            if (!$queryFilter->safe($request['query_string'], $request['configs'])) {
                $this->handleService($request['query_string'], $filter, $request['request_method']);
            } else {
                return parent::handle($filter, $request);
            }

        }
        return parent::handle($filter, $request);

    }
}