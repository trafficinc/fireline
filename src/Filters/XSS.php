<?php

namespace Filters;

use \BaseFilter;

class XSS extends BaseFilter
{
    /**
     * @var string
     */
    protected $compares_file = "";

    public function safe(string $value, array $configs): bool
    {
        return $this->unsafeEngineRuleFor($value, $configs, ['xss']) === null;
    }
}
