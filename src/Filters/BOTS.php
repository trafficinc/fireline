<?php

namespace Filters;

use BaseFilter;
use Fireline\Engine\BotGuard;

class BOTS extends BaseFilter
{
    /**
     * @var string
     */
    protected $compares_file = "bots.php";
    protected $found = '';

    /**
     * Check given string
     *
     * @param string $value
     * @return bool
     */
    public function safe(string $value, array $configs): bool {
        $guard = new BotGuard($this->compares);
        if ($guard->safe($value)) {
            return true;
        }

        $this->found = $guard->found();

        return false;
    }

    public function getFound(): string {
        return $this->found;
    }

}
