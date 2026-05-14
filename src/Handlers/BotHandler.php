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

            return $this->blockOrForward($bots->safe($userAgent) ? null : $bots->found(), $filter, $request);
        }

        return parent::handle($filter, $request);
    }
}
