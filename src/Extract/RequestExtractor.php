<?php

namespace Fireline\Extract;

class RequestExtractor
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function capture(): array
    {
        $headers = $this->headers();
        $body = $this->body();

        return [
            'ip' => get_ip($this->config['trusted_proxies']),
            'headers' => $headers,
            'method' => get_request_method(),
            'route' => $this->route(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $headers['User-Agent'] ?? '',
            'referer' => get_referer(),
            'query_string' => get_query_string(),
            'body' => $body,
            'fields' => $this->fields($headers, $body),
        ];
    }

    public function extractFields(array $request): array
    {
        return $request['fields'];
    }

    protected function headers(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = (string) $value;
            }
        }

        return $headers;
    }

    protected function body(): string
    {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            return '';
        }

        return $this->cap((string) $body);
    }

    protected function route(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    protected function fields(array $headers, string $body): array
    {
        $fields = [];
        $fields = array_merge($fields, $this->flatten($_GET, 'get', 'get'));
        $fields = array_merge($fields, $this->flatten($_POST, 'post', 'post'));
        $fields = array_merge($fields, $this->flatten($_COOKIE, 'cookie', 'cookie'));

        if (get_query_string() !== '') {
            $fields[] = new RequestField('query_string', get_query_string(), 'query');
        }

        if ($this->config['inspect_headers']) {
            unset($headers['Cookie']);
            $fields = array_merge($fields, $this->flatten($headers, 'header', 'header'));
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if ($this->config['inspect_json'] && stripos($contentType, 'application/json') !== false) {
            $fields = array_merge($fields, $this->flatten(JsonExtractor::extract($body), 'json', 'json'));
        }

        if ($this->shouldInspectRawBody($contentType, $body)) {
            $fields[] = new RequestField('raw.body', $body, 'raw');
        }

        return $fields;
    }

    protected function shouldInspectRawBody(string $contentType, string $body): bool
    {
        if (!$this->config['inspect_raw_body'] || $body === '') {
            return false;
        }

        return stripos($contentType, 'application/json') === false
            && stripos($contentType, 'application/x-www-form-urlencoded') === false
            && stripos($contentType, 'multipart/form-data') === false;
    }

    protected function flatten(array $values, string $prefix, string $source): array
    {
        $fields = [];
        foreach ($values as $key => $value) {
            $name = $prefix . '.' . (string) $key;
            if (is_array($value)) {
                $fields = array_merge($fields, $this->flatten($value, $name, $source));
                continue;
            }

            if (is_object($value)) {
                $value = method_exists($value, '__toString') ? (string) $value : json_encode($value);
            }

            $fields[] = new RequestField($name, $this->cap((string) ($value ?? '')), $source);
        }

        return $fields;
    }

    protected function cap(string $value): string
    {
        return strlen($value) > $this->config['max_value_length']
            ? substr($value, 0, $this->config['max_value_length'])
            : $value;
    }
}
