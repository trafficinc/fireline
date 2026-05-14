<?php

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    return;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/src/BaseFilter.php';
require_once __DIR__ . '/src/LogService.php';
require_once __DIR__ . '/src/Fireline.php';

spl_autoload_register(
    function ($className)
    {
        $file = __DIR__ . "/src/" . str_replace("\\", "/", $className) . ".php";
        if (is_file($file)) {
            require_once $file;
        }
    }
);
