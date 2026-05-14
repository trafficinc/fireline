<?php

namespace Handlers;

use LogService;

class SqlHandler extends AbstractHandler {
    use LogService;

    public function handle(string $filter, array $request): ?string{
        if ($filter === 'sql') {
            $badValue = $this->firstUnsafeValue(null, $request);
            if ($badValue !== null) {
                $this->handleService($badValue, $filter, $request['request_method']);
            } else {
                return parent::handle($filter, $request);
            }

        }
        return parent::handle($filter, $request);

    }
}
