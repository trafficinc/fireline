<?php

use PHPUnit\Framework\TestCase;

class TestableLogService
{
    use LogService;

    private $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    protected function logFilePath(): string
    {
        return $this->path;
    }
}

class LogServiceTest extends TestCase
{
    private $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/fireline-log-test-' . uniqid('', true) . '/fireline.log';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['REQUEST_URI'] = "/login\nforged";
        $_SERVER['HTTP_USER_AGENT'] = "Mozilla/5.0\r\nInjected";
        $_SERVER['HTTP_REFERER'] = 'https://example.com/form';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_REFERER']);
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }

        $dir = dirname($this->logFile);
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    public function testLogWritesJsonLineAndNormalizesControlCharacters(): void
    {
        $logger = new TestableLogService($this->logFile);

        $logger->log("bad\nvalue", 'sql', "POST\r\nx");

        $contents = trim(file_get_contents($this->logFile));
        $event = json_decode($contents, true);

        $this->assertIsArray($event);
        $this->assertSame('warn', $event['level']);
        $this->assertSame('fireline.blocked_request', $event['event']);
        $this->assertSame('sql', $event['filter']);
        $this->assertSame('POST  x', $event['method']);
        $this->assertSame('bad value', $event['value']);
        $this->assertSame('/login forged', $event['request_uri']);
        $this->assertSame('Mozilla/5.0  Injected', $event['user_agent']);
    }

    public function testLogRedactsCommonSecretValues(): void
    {
        $logger = new TestableLogService($this->logFile);

        $logger->log('username=admin&password=secret-token&api_key=abc123', 'queryString', 'GET');

        $event = json_decode(trim(file_get_contents($this->logFile)), true);

        $this->assertSame('username=admin&password=[redacted]&api_key=[redacted]', $event['value']);
    }

    public function testLogCapsLongValues(): void
    {
        $logger = new TestableLogService($this->logFile);

        $logger->log(str_repeat('a', 1200), 'xss', 'POST');

        $event = json_decode(trim(file_get_contents($this->logFile)), true);

        $this->assertSame(1003, strlen($event['value']));
        $this->assertStringEndsWith('...', $event['value']);
    }
}
