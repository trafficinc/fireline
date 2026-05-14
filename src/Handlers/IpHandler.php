<?php

namespace Handlers;

use Fireline\Engine\IpGuard;
use LogService;

class IpHandler extends AbstractHandler
{
    use LogService;

    public function handle(string $filter, array $request): ?string{
        if ($filter === 'ip') {
            $ips = new IpGuard();

            return $this->blockOrForward(
                $ips->safe((string) ($request['ip'] ?? ''), (array) ($request['configs'] ?? [])) ? null : (string) ($request['ip'] ?? ''),
                $filter,
                $request
            );
        }

        return parent::handle($filter, $request);
    }
}
