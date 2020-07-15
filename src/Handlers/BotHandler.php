<?php

namespace Handlers;

use Filters\BOTS;
use LogService;

class BotHandler extends AbstractHandler
{
    use LogService;

    public function handle(string $filter, array $request): ?string
    {
        if ($filter === "bot") {

            $bots = new BOTS();
            $botSafe = $bots->safe($request['headers']['User-Agent'], $request['configs']);
            if (!$botSafe) {
                $this->handleService($bots->getFound(), $filter, $request['request_method']);
            } else {
                return parent::handle($filter, $request);
            }
        }
        return parent::handle($filter, $request);

    }
}