<?php

namespace Filters;

use BaseFilter;

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

        foreach ($this->compares as $compared)
        {
            $compared = trim($compared);
            if (empty($compared) || strpos($compared, '#') === 0){
                continue;
            }
            if ($this->ruleMatches($compared, $value)){

                if (empty($value)){
                    // empty User-Agent
                    $this->found  = '[SystemProduced] Empty User-Agent';
                } else {
                    // save user-agent
                    $this->found  = $value;
                }
                return false;
            }
        }
        return true;
    }

    public function getFound(): string {
        return $this->found;
    }

}
