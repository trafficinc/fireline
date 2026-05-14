<?php

namespace Handlers;

use LogService;

class SqlHandler extends AbstractHandler {
    use LogService;

    public function handle(string $filter, array $request): ?string{
        if ($filter === 'sql') {
            return $this->blockOrForward($this->firstUnsafeValue($request, ['sqli']), $filter, $request);
        }

        return parent::handle($filter, $request);
    }
}
