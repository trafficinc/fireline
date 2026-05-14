<?php

namespace Handlers;

use Fireline\Engine\BotGuard;
use LogService;

class BotHandler extends AbstractHandler
{
    use LogService;

    public function handle(string $filter, array $request): ?string
    {
        if ($filter === "bot") {

            $bots = new BotGuard();
            $userAgent = $request['headers']['User-Agent'] ?? '';
            $botSafe = $bots->safe($userAgent);
            if (!$botSafe) {
                $this->handleService($bots->found(), $filter, $request['request_method']);
            } else {
                return parent::handle($filter, $request);
            }
        }
        return parent::handle($filter, $request);

    }
}
