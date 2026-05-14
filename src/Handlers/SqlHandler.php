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
            $badValue = '';
            foreach ($request['get_request_method'] as $key => $val) {
                if (!$sql->safe($val,$request['configs'])) {
                    $badCharacter = true;
                    $badValue = $val;
                    break;
                }
            }

            // filter POST requests
            if (!$badCharacter) {
                foreach ($request['post_request_method'] as $key => $val) {
                    if (!$sql->safe($val,$request['configs'])) {
                        $badCharacter = true;
                        $badValue = $val;
                        break;
                    }
                }
            }

            if ($badCharacter) {
                $this->handleService($badValue, $filter, $request['request_method']);
            } else {
                return parent::handle($filter, $request);
            }

        }
        return parent::handle($filter, $request);

    }
}
