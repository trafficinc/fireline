<?php

require __DIR__ . '/../autoload.php';
require __DIR__ . '/../helpers.php';

class FirelineSmokeTest extends FireLine
{
    public function flatten(array $values, string $prefix = ''): array
    {
        return $this->flattenRequestValues($values, $prefix);
    }

    public function headers(array $headers): array
    {
        return $this->getHeaderRequestValues($headers);
    }
}

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assertTrueValue(bool $actual, string $message): void
{
    assertSameValue(true, $actual, $message);
}

function assertFalseValue(bool $actual, string $message): void
{
    assertSameValue(false, $actual, $message);
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
assertSameValue('203.0.113.10', get_ip([]), 'untrusted proxy headers are ignored');
assertSameValue('198.51.100.1', get_ip(['203.0.113.10']), 'trusted proxy headers are accepted');

$fireline = new FirelineSmokeTest(['max_value_length' => 10]);
assertSameValue(['json.a.b' => '<script>'], $fireline->flatten(['a' => ['b' => '<script>']], 'json'), 'nested values are flattened');
assertSameValue(['body' => '0123456789'], $fireline->flatten(['body' => '0123456789abc'], ''), 'values are length capped');
assertSameValue(['User-Agent' => 'curl'], $fireline->headers(['User-Agent' => 'curl', 'Cookie' => 'a=b']), 'cookie header is excluded from header scanning');

$query = new Filters\Query();
assertTrueValue($query->safe('asset=app.js', []), 'normal js asset query passes');
assertFalseValue($query->safe('cmd=/bin/sh', []), 'bin shell query blocks');
assertFalseValue($query->safe('q=javascript:alert(1)', []), 'javascript query blocks');

$sql = new Filters\SQL();
assertFalseValue($sql->safe('1 union select password from users', []), 'sql union payload blocks');

$xss = new Filters\XSS();
assertFalseValue($xss->safe('<script>alert(1)</script>', []), 'script tag blocks');

$bots = new Filters\BOTS();
assertFalseValue($bots->safe('', []), 'empty user agent blocks');

echo "Smoke tests passed.\n";
