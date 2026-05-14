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
     * @var array
     */
    protected $configs = [
        'bypass_firewall' => false,
        'strict_mode' => false,
        'ip_by_country' => false,
        'whitelist' => false,
        'trusted_proxies' => [],
        'max_value_length' => 8192,
        'inspect_json' => true,
        'inspect_headers' => true,
        'inspect_raw_body' => true,
    ];

    /**
     * fireline constructor.
     */
    public function __construct(array $configs = []){
        $this->configs = array_merge($this->configs, $this->loadConfig(), $configs);
        $this->configs = $this->validateConfig($this->configs);
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

    protected function loadConfig(): array {
        $configFile = dirname(__DIR__) . '/config.php';
        if (!is_readable($configFile)) {
            return [];
        }

        $configs = require $configFile;
        return is_array($configs) ? $configs : [];
    }

    protected function validateConfig(array $configs): array {
        foreach (['bypass_firewall', 'strict_mode', 'ip_by_country', 'whitelist', 'inspect_json', 'inspect_headers', 'inspect_raw_body'] as $key) {
            $configs[$key] = filter_var($configs[$key], FILTER_VALIDATE_BOOLEAN);
        }

        if (!is_array($configs['trusted_proxies'])) {
            $configs['trusted_proxies'] = [];
        }

        $configs['max_value_length'] = (int) $configs['max_value_length'];
        if ($configs['max_value_length'] < 1) {
            $configs['max_value_length'] = 8192;
        }

        return $configs;
    }

    protected function getRequestBody(): string {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            return '';
        }

        if (strlen($body) > $this->configs['max_value_length']) {
            return substr($body, 0, $this->configs['max_value_length']);
        }

        return $body;
    }

    protected function getJsonRequestValues(string $body): array {
        if (!$this->configs['inspect_json']) {
            return [];
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'application/json') === false) {
            return [];
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return ['raw_json' => $body];
        }

        return $decoded;
    }

    protected function getRawRequestValues(string $body): array {
        if (!$this->configs['inspect_raw_body'] || $body === '') {
            return [];
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (
            stripos($contentType, 'application/json') !== false ||
            stripos($contentType, 'application/x-www-form-urlencoded') !== false ||
            stripos($contentType, 'multipart/form-data') !== false
        ) {
            return [];
        }

        return ['body' => $body];
    }

    protected function getHeaderRequestValues(array $headers): array {
        if (!$this->configs['inspect_headers']) {
            return [];
        }

        unset($headers['Cookie']);
        return $headers;
    }

    protected function flattenRequestValues(array $values, string $prefix = ''): array {
        $flattened = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

            if (is_array($value)) {
                $flattened = array_merge($flattened, $this->flattenRequestValues($value, $path));
                continue;
            }

            if (is_object($value)) {
                $value = method_exists($value, '__toString') ? (string) $value : json_encode($value);
            }

            if ($value === null) {
                $value = '';
            }

            $value = (string) $value;
            if (strlen($value) > $this->configs['max_value_length']) {
                $value = substr($value, 0, $this->configs['max_value_length']);
            }

            $flattened[$path] = $value;
        }

        return $flattened;
    }

    protected function runFireWall(Handler $handler) {
        //echo xdebug_time_index(), "\n";
        $headers = $this->get_headers();
        $body = $this->getRequestBody();
        $inputValues = array_merge(
            $this->flattenRequestValues($_GET, 'get'),
            $this->flattenRequestValues($_POST, 'post'),
            $this->flattenRequestValues($_COOKIE, 'cookie'),
            $this->flattenRequestValues($this->getHeaderRequestValues($headers), 'header'),
            $this->flattenRequestValues($this->getJsonRequestValues($body), 'json'),
            $this->flattenRequestValues($this->getRawRequestValues($body), 'raw')
        );

        $current_request = [
            'ip' => get_ip($this->configs['trusted_proxies']),
            'headers' => $headers,
            'request_method' => get_request_method(),
            'get_request_method' => $inputValues,
            'post_request_method' => [],
            'referrer' => get_referer(),
            'user_agent' => get_user_agent(),
            'query_string' => get_query_string(),
            'configs' => $this->configs,
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
