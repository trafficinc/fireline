<?php

namespace Handlers;
use Filters\IP;
use LogService;


class IpHandler extends AbstractHandler
{
    use LogService;

    public function handle(string $filter, array $request): ?string{
        if ($filter === 'ip') {
            $ips = new IP();
            $ipSafe = $ips->safe($request['ip'],$request['configs']);
            if (!$ipSafe) {
                $this->handleService($request['ip'], $filter, $request['request_method']);
            } else {
                return parent::handle($filter, $request);
            }
        }
        return parent::handle($filter, $request);
    }
}