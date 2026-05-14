<?php

use Fireline\Engine\RequestLimits;
use Fireline\Extract\RequestField;
use PHPUnit\Framework\TestCase;

class RequestLimitsTest extends TestCase
{
    public function testBlocksTooManyFields(): void
    {
        $result = (new RequestLimits($this->config(['max_fields' => 1]), 25))->inspect([
            'headers' => [],
            'fields' => [
                new RequestField('get.a', '1', 'get'),
                new RequestField('get.b', '2', 'get'),
            ],
            'query_string' => '',
            'body' => '',
            'raw_body_length' => 0,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('REQUEST_LIMIT_MAX_FIELDS', $result->toArray()['matches'][0]['id']);
        $this->assertSame(25, $result->score());
    }

    public function testBlocksOversizedBodyBeforeFieldScanning(): void
    {
        $result = (new RequestLimits($this->config(['max_body_length' => 10]), 25))->inspect([
            'headers' => [],
            'fields' => [],
            'query_string' => '',
            'body' => 'truncated',
            'raw_body_length' => 11,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('REQUEST_LIMIT_MAX_BODY_LENGTH', $result->toArray()['matches'][0]['id']);
    }

    public function testBlocksMalformedEncoding(): void
    {
        $result = (new RequestLimits($this->config(), 25))->inspect([
            'headers' => [],
            'fields' => [new RequestField('get.q', 'abc%zz', 'get')],
            'query_string' => 'q=abc%zz',
            'body' => '',
            'raw_body_length' => 0,
        ]);

        $this->assertNotNull($result);
        $this->assertContains('REQUEST_LIMIT_MALFORMED_URL_ENCODING', array_column($result->toArray()['matches'], 'id'));
    }

    public function testAllowsRequestWithinLimits(): void
    {
        $result = (new RequestLimits($this->config(), 25))->inspect([
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
            'fields' => [new RequestField('get.q', 'washers', 'get')],
            'query_string' => 'q=washers',
            'body' => '',
            'raw_body_length' => 0,
        ]);

        $this->assertNull($result);
    }

    protected function config(array $overrides = []): array
    {
        return array_merge([
            'max_fields' => 200,
            'max_headers' => 100,
            'max_header_length' => 8192,
            'max_body_length' => 1048576,
        ], $overrides);
    }
}
