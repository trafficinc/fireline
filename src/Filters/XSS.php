<?php

namespace Filters;

use \BaseFilter;

class XSS extends BaseFilter
{
    /**
     * @var string
     */
    protected $compares_file = "xss.php";

    /**
     * Check given string
     *
     * @param string $value
     * @return bool
     */
    public function safe(string $value, array $configs): bool
    {
        foreach ($this->compares as $compared)
        {
            $compared = trim($compared);
            if (empty($compared) || strpos($compared, '#') === 0){
                continue;
            }
            if ($this->ruleMatches($compared, $value)){
                return false;
            }
        }
        return true;

    }
}
