<?php

namespace Filters;

use \BaseFilter;

class Query extends BaseFilter
{
    /**
     * @var string
     */
    protected $compares_file = "query.php";

    /**
     * Check given string
     *
     * @param string $value
     * @return bool
     */
    public function safe(string $value, array $configs): bool
    {
        $strict_mode = $configs['strict_mode'];
        foreach ($this->compares as $compared)
        {
            $compared = trim($compared);
            if (empty($compared) || strpos($compared, '#') === 0){
                continue;
            }

            if ($strict_mode) {
                $value = $this->normalize($value);
            }

            // Regex Firewall Rules.
            preg_match('/'.$compared.'/i',$value,$matches);

            if (!empty($matches)){
                return false;
            }
        }
        return true;
    }

    private function normalize($string){
        // allow only letters
        $res = preg_replace("/[^a-zA-Z\/=]/", "", $string);
        // make lowercase
        $res = strtolower($res);
        // return
        return $res;
    }
}