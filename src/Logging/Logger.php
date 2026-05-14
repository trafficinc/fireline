<?php

namespace Fireline\Logging;

use Fireline\Engine\Decision;

class Logger
{
    public static function blocked(Decision $decision): void
    {
        (new AsyncWriter())->write(EventFormatter::blockedDecision($decision));
    }
}
