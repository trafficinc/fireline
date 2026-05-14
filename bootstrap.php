<?php

require __DIR__ . '/autoload.php';

use Fireline\Engine\ResponseHandler;
use Fireline\Engine\WafEngine;

$waf = new WafEngine();
$decision = $waf->inspectCurrentRequest();

if ($decision->shouldBlock()) {
    ResponseHandler::block($decision);
}
