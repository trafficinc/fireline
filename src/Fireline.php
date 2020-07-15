<?php

use Handlers\BotHandler;
use Handlers\Handler;
use Handlers\IpHandler;
use Handlers\QueryHandler;
use Handlers\SqlHandler;
use Handlers\XssHandler;

/*
 * PHP 7.1  or above required
 * */

class FireLine {
    use LogService;

    /**
     * fireline constructor.
     */
    public function __construct(){
    }

    public function run() {
        $ips            = new IpHandler;
        $queryString    = new QueryHandler;
        $bots           = new BotHandler;
        $sql            = new SqlHandler;
        $xss            = new XssHandler;

        $ips->setNext($queryString)->setNext($bots)->setNext($sql)->setNext($xss);

        $this->runFireWall($ips);
    }

    protected function get_headers(): array {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    protected function runFireWall(Handler $handler) {
        //echo xdebug_time_index(), "\n";

        $current_request = [
            'ip' => get_ip(),
            'headers' => $this->get_headers(),
            'request_method' => get_request_method(),
            'get_request_method' => $_GET,
            'post_request_method' => $_POST,
            'referrer' => get_referer(),
            'user_agent' => get_user_agent(),
            'query_string' => get_query_string(),
            'configs' => [
                'bypass_firewall' => false,
                'strict_mode' => false,
                'ip_by_country' => false,
                'whitelist' => false,
            ],
        ];

        if (!$current_request['configs']['bypass_firewall']) {
            $filterGroup = ["ip", "queryString", "bot", "sql", "xss"];
            foreach ($filterGroup as $filter) {
                $handler->handle($filter, $current_request);
            }
        }

       // echo xdebug_time_index(), "\n";

    }

}


