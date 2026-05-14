<?php

use Fireline\Engine\ResponseHandler;
use Fireline\Engine\WafEngine;

/*
 * PHP 7.1 or above required
 */

class FireLine {
    /**
     * @var array
     */
    protected $configs = [];

    /**
     * FireLine constructor.
     */
    public function __construct(array $configs = []){
        $this->configs = $configs;
    }

    public function run() {
        $decision = (new WafEngine($this->configs))->inspectCurrentRequest();

        if ($decision->shouldBlock()) {
            ResponseHandler::block($decision);
        }
    }
}
