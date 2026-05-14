<?php

namespace Handlers;

use Filters\SQL;
use LogService;

class SqlHandler extends AbstractHandler {
    use LogService;

    public function handle(string $filter, array $request): ?string{
        $sql = new SQL();
        if ($filter === 'sql') {
            $badCharacter = false;
            foreach ($request['get_request_method'] as $key => $val) {
                if (!$sql->safe($val,$request['configs'])) {
                    $badCharacter = true;
                }
            }

            // filter POST requests
            foreach ($request['post_request_method'] as $key => $val) {
                if (!$sql->safe($val,$request['configs'])) {
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