<?php

namespace Handlers;

use LogService;

class XssHandler extends AbstractHandler
{
    use LogService;

    public function handle(string $filter, array $request): ?string
    {
        if ($filter === 'xss') {
            return $this->blockOrForward($this->firstUnsafeValue($request, ['xss']), $filter, $request);
        }

        return parent::handle($filter, $request);
    }
}
