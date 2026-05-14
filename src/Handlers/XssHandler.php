<?php

namespace Handlers;

use Filters\XSS;
use LogService;

class XssHandler extends AbstractHandler
{
    use LogService;

    public function handle(string $filter, array $request): ?string
    {
        if ($filter === 'xss') {
            $xss = new XSS();
            $badValue = $this->firstUnsafeValue($xss, $request);
            if ($badValue !== null) {
                $this->handleService($badValue, $filter, $request['request_method']);
            } else {
                return parent::handle($filter, $request);
            }

        }
        return parent::handle($filter, $request);
    }
}
