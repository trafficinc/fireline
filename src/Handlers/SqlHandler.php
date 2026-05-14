<?php

namespace Handlers;

use Filters\SQL;
use LogService;

class SqlHandler extends AbstractHandler {
    use LogService;

    public function handle(string $filter, array $request): ?string{
        $sql = new SQL();
        if ($filter === 'sql') {
            $badValue = $this->firstUnsafeValue($sql, $request);
            if ($badValue !== null) {
                $this->handleService($badValue, $filter, $request['request_method']);
            } else {
                return parent::handle($filter, $request);
            }

        }
        return parent::handle($filter, $request);

    }
}
