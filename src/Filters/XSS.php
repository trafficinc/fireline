<?php

namespace Filters;

use \BaseFilter;

class XSS extends BaseFilter
{
    /**
     * @var string
     */
    protected $compares_file = "xss.php";
}
