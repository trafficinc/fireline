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
        if (strpos($className, 'Fireline\\') === 0) {
            $className = substr($className, strlen('Fireline\\'));
            $file = __DIR__ . "/src/" . str_replace("\\", "/", $className) . ".php";
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }

        if (strpos($className, 'Cli\\') === 0) {
            $className = substr($className, strlen('Cli\\'));
            $file = __DIR__ . "/cli/Cli/" . str_replace("\\", "/", $className) . ".php";
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }

        $file = __DIR__ . "/src/" . str_replace("\\", "/", $className) . ".php";
        if (is_file($file)) {
            require_once $file;
        }
    }
);
