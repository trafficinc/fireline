<?php

abstract class BaseFilter
{
    /**
     * @var array
     */
    protected $compares = [];

    /**
     * @var string
     */
    protected $compares_file = "";

    /**
     * BaseFilter constructor.
     */
    public function __construct(){
        $compares = require __DIR__ . '/Compares/' . $this->compares_file;

        if ($compares !== false) {
            $this->compares = $compares;
        }

    }

    /**
     * Check given string
     *
     * @param string $value
     * @param array $configs
     * @return bool
     */
    abstract public function safe(string $value, array $configs): bool;
}