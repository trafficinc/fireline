<?php

namespace Filters;

use BaseFilter;

class SQL extends BaseFilter
{
    /**
     * @var string
     */
    protected $compares_file = "sql.php";

    public function safe(string $value, array $configs): bool
    {
        return $this->unsafeEngineRuleFor($value, $configs, ['sqli']) === null;
    }
}
