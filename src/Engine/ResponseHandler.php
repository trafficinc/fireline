<?php

namespace Fireline\Engine;

use Fireline\Logging\Logger;

class ResponseHandler
{
    public static function block(Decision $decision): void
    {
        Logger::blocked($decision);
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
}
