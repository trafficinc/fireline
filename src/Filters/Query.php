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
        $strict_mode = $configs['strict_mode'] ?? false;

        if ($strict_mode) {
            $value = $this->normalize($value);
        }

        return $this->unsafeEngineRuleFor($value, $configs, ['xss', 'shell', 'lfi', 'rfi', 'webshell', 'scanner']) === null;
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
