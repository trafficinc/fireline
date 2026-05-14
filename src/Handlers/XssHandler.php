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
            $badCharacter = false;
            foreach ($request['get_request_method'] as $key => $val) {
                if (!$xss->safe($val,$request['configs'])) {
                    $badCharacter = true;
                }
            }

            // filter POST requests
            foreach ($request['post_request_method'] as $key => $val) {
                if (!$xss->safe($val,$request['configs'])) {
                    $badCharacter = true;
                }
            }

            if ($badCharacter) {
                $this->handleService($val, $filter, $request['request_method']);
            } else {
                return parent::handle($filter, $request);
            }

        }
        return parent::handle($filter, $request);
    }
}