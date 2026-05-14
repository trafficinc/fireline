<?php

require __DIR__ . '/../autoload.php';
require __DIR__ . '/../helpers.php';

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

function fieldMap(array $fields): array
{
    $map = [];
    foreach ($fields as $field) {
        $map[$field->name()] = $field->value();
    }

    return $map;
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
assertSameValue('203.0.113.10', get_ip([]), 'untrusted proxy headers are ignored');
assertSameValue('198.51.100.1', get_ip(['203.0.113.10']), 'trusted proxy headers are accepted');

$_GET = ['a' => ['b' => '<script>'], 'body' => '0123456789abc'];
$_POST = [];
$_COOKIE = [];
$_SERVER['HTTP_USER_AGENT'] = 'curl';
$_SERVER['HTTP_COOKIE'] = 'a=b';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

$extractor = new Fireline\Extract\RequestExtractor([
    'trusted_proxies' => [],
    'max_value_length' => 10,
    'inspect_json' => true,
    'inspect_headers' => true,
    'inspect_raw_body' => true,
]);
$fields = fieldMap($extractor->extractFields($extractor->capture()));

assertSameValue('<script>', $fields['get.a.b'], 'nested values are flattened');
assertSameValue('0123456789', $fields['get.body'], 'values are length capped');
assertSameValue('curl', $fields['header.User-Agent'], 'user-agent header is inspected');
assertSameValue(false, array_key_exists('header.Cookie', $fields), 'cookie header is excluded from header scanning');

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
