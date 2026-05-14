<?php

use Handlers\BotHandler;
use Handlers\Handler;
use Handlers\IpHandler;
use Handlers\QueryHandler;
use Handlers\SqlHandler;
use Handlers\XssHandler;
use PHPUnit\Framework\TestCase;

trait RecordsBlockedRequests
{
    public $blocked = false;
    public $blockedValue = null;
    public $blockedFilter = null;
    public $blockedRequestMethod = null;

    public function handleService($value, $filter, $request)
    {
        $this->blocked = true;
        $this->blockedValue = $value;
        $this->blockedFilter = $filter;
        $this->blockedRequestMethod = $request;
    }
}

class RecordingSqlHandler extends SqlHandler
{
    use RecordsBlockedRequests;
}

class RecordingXssHandler extends XssHandler
{
    use RecordsBlockedRequests;
}

class RecordingQueryHandler extends QueryHandler
{
    use RecordsBlockedRequests;
}

class RecordingBotHandler extends BotHandler
{
    use RecordsBlockedRequests;
}

class RecordingIpHandler extends IpHandler
{
    use RecordsBlockedRequests;
}

class RecordingTerminalHandler implements Handler
{
    public $handledFilter = null;
    public $handledRequest = null;

    public function setNext(Handler $handler): Handler
    {
        return $handler;
    }

    public function handle(string $type, array $request): ?string
    {
        $this->handledFilter = $type;
        $this->handledRequest = $request;

        return 'forwarded';
    }
}

class HandlersTest extends TestCase
{
    private function request(array $overrides = []): array
    {
        return array_merge([
            'ip' => '192.0.2.10',
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
            'request_method' => 'GET',
            'get_request_method' => [],
            'post_request_method' => [],
            'query_string' => '',
            'configs' => [
                'bypass_firewall' => false,
                'strict_mode' => false,
                'ip_by_country' => false,
                'whitelist' => false,
            ],
        ], $overrides);
    }

    public function testSqlHandlerBlocksMaliciousGetValue(): void
    {
        $handler = new RecordingSqlHandler();

        $handler->handle('sql', $this->request([
            'get_request_method' => ['get.id' => '1 union select password from users'],
        ]));

        $this->assertTrue($handler->blocked);
        $this->assertSame('sql', $handler->blockedFilter);
        $this->assertSame('1 union select password from users', $handler->blockedValue);
    }

    public function testSqlHandlerAllowsBenignValues(): void
    {
        $handler = new RecordingSqlHandler();

        $handler->handle('sql', $this->request([
            'get_request_method' => ['get.q' => 'stainless steel washers'],
            'post_request_method' => ['post.qty' => '100'],
        ]));

        $this->assertFalse($handler->blocked);
    }

    public function testSqlHandlerForwardsBenignRequestsToNextHandler(): void
    {
        $handler = new RecordingSqlHandler();
        $terminal = new RecordingTerminalHandler();
        $handler->setNext($terminal);
        $request = $this->request([
            'get_request_method' => ['get.q' => 'stainless steel washers'],
        ]);

        $result = $handler->handle('sql', $request);

        $this->assertSame('forwarded', $result);
        $this->assertFalse($handler->blocked);
        $this->assertSame('sql', $terminal->handledFilter);
        $this->assertSame($request, $terminal->handledRequest);
    }

    public function testXssHandlerBlocksMaliciousPostValue(): void
    {
        $handler = new RecordingXssHandler();

        $handler->handle('xss', $this->request([
            'post_request_method' => ['post.comment' => '<script>alert(1)</script>'],
        ]));

        $this->assertTrue($handler->blocked);
        $this->assertSame('xss', $handler->blockedFilter);
        $this->assertSame('<script>alert(1)</script>', $handler->blockedValue);
    }

    public function testXssHandlerAllowsBenignValues(): void
    {
        $handler = new RecordingXssHandler();

        $handler->handle('xss', $this->request([
            'get_request_method' => ['get.q' => 'price is < 10 and quantity > 2'],
        ]));

        $this->assertFalse($handler->blocked);
    }

    public function testXssHandlerForwardsBenignRequestsToNextHandler(): void
    {
        $handler = new RecordingXssHandler();
        $terminal = new RecordingTerminalHandler();
        $handler->setNext($terminal);
        $request = $this->request([
            'get_request_method' => ['get.q' => 'price is < 10 and quantity > 2'],
        ]);

        $result = $handler->handle('xss', $request);

        $this->assertSame('forwarded', $result);
        $this->assertFalse($handler->blocked);
        $this->assertSame('xss', $terminal->handledFilter);
        $this->assertSame($request, $terminal->handledRequest);
    }

    public function testQueryHandlerBlocksMaliciousQueryString(): void
    {
        $handler = new RecordingQueryHandler();

        $handler->handle('queryString', $this->request([
            'query_string' => 'cmd=/bin/sh',
        ]));

        $this->assertTrue($handler->blocked);
        $this->assertSame('queryString', $handler->blockedFilter);
    }

    public function testQueryHandlerAllowsCommonQueryString(): void
    {
        $handler = new RecordingQueryHandler();

        $handler->handle('queryString', $this->request([
            'query_string' => 'asset=app.js&q=union hardware',
        ]));

        $this->assertFalse($handler->blocked);
    }

    public function testBotHandlerBlocksMissingUserAgent(): void
    {
        $handler = new RecordingBotHandler();

        $handler->handle('bot', $this->request([
            'headers' => [],
        ]));

        $this->assertTrue($handler->blocked);
        $this->assertSame('bot', $handler->blockedFilter);
    }

    public function testBotHandlerAllowsNormalUserAgent(): void
    {
        $handler = new RecordingBotHandler();

        $handler->handle('bot', $this->request([
            'headers' => ['User-Agent' => 'Mozilla/5.0 AppleWebKit/537.36 Chrome/120 Safari/537.36'],
        ]));

        $this->assertFalse($handler->blocked);
    }

    public function testIpHandlerBlocksUnsafeIp(): void
    {
        $handler = new RecordingIpHandler();

        $handler->handle('ip', $this->request([
            'ip' => '203.0.113.10',
            'configs' => [
                'ip_by_country' => false,
                'whitelist' => true,
            ],
        ]));

        $this->assertTrue($handler->blocked);
        $this->assertSame('ip', $handler->blockedFilter);
        $this->assertSame('203.0.113.10', $handler->blockedValue);
    }

    public function testIpHandlerAllowsUnlistedIp(): void
    {
        $handler = new RecordingIpHandler();

        $handler->handle('ip', $this->request([
            'ip' => '192.0.2.10',
            'configs' => [
                'ip_by_country' => false,
                'whitelist' => false,
            ],
        ]));

        $this->assertFalse($handler->blocked);
    }
}
