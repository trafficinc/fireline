# Fireline

Fireline is a low-configuration PHP web application firewall request blocker. It is designed to be loaded before an application request and block obvious malicious traffic before the application handles it.

Fireline currently inspects:

- Client IP address
- Query string
- User agent
- GET, POST, cookie, header, JSON, and selected raw body values
- SQL injection, XSS, query abuse, and bot patterns

## Requirements

- PHP 7.1 or newer for runtime compatibility
- PHP 8.1 or newer for the included development tools
- `ext-json`
- Writable `storage/logs/fireline.log`

Composer can be run with the checked-in PHAR:

```bash
php composer.phar install
```

Or with Composer installed globally:

```bash
composer install
```

## Install

Place the `fireline` directory outside the public web root, then copy [config-webroot/fireline.php](config-webroot/fireline.php) into the web root.

Example layout:

```text
project/
  fireline/
  public/
    fireline.php
```

Then configure PHP to prepend the copied web-root file before application requests.

For `.htaccess` or Apache PHP config:

```apacheconf
php_value auto_prepend_file "/full/path/to/public/fireline.php"
```

For `.user.ini`:

```ini
auto_prepend_file = fireline.php
```

## Web Usage

The web-root `fireline.php` file loads Fireline and runs it:

```php
<?php

include __DIR__ . '/fireline/index.php';
$waf = new FireLine();
$waf->run();
```

If Fireline detects a blocked request, it logs the event, sends:

```text
HTTP/1.1 403 Forbidden
```

and exits before the application continues.

## Configuration

Fireline works without a config file. To override defaults, copy [config.php.example](config.php.example) to `config.php` in the Fireline directory.

```php
<?php

return [
    'bypass_firewall' => false,
    'strict_mode' => false,
    'ip_by_country' => false,
    'whitelist' => false,
    'trusted_proxies' => [],
    'max_value_length' => 8192,
    'inspect_json' => true,
    'inspect_headers' => true,
    'inspect_raw_body' => true,
    'score_threshold' => 25,
    'regex_threshold' => 10,
    'safe_cache_threshold' => 3,
];
```

Config options:

- `bypass_firewall`: disables all filtering when set to `true`.
- `strict_mode`: normalizes query strings before query filtering.
- `ip_by_country`: enables country blocking using `src/GeoLite2-Country.mmdb` and [src/Compares/ip_block_by_country.php](src/Compares/ip_block_by_country.php).
- `whitelist`: enables IP whitelist mode using [src/Compares/ips_white_list.php](src/Compares/ips_white_list.php). When enabled, IP blacklist mode is not used.
- `trusted_proxies`: proxy IPs or CIDR ranges allowed to supply `X-Forwarded-For`.
- `max_value_length`: maximum characters inspected per request value.
- `inspect_json`: inspects JSON request bodies for `application/json` requests.
- `inspect_headers`: inspects HTTP headers, excluding `Cookie` because cookies are inspected separately.
- `inspect_raw_body`: inspects raw bodies for non-form and non-JSON content types.
- `score_threshold`: field score required to block a request.
- `regex_threshold`: score required before conditional regex rules run.
- `safe_cache_threshold`: maximum score eligible for short-lived safe fingerprint caching.

## Architecture

The current engine follows the staged pipeline described in [AGENTS.md](AGENTS.md):

1. Extract request fields individually.
2. Normalize each field once.
3. Build a route/field/shape fingerprint.
4. Check short-lived safe cache.
5. Run cheap prefilters and heuristics.
6. Run keyword scanning.
7. Run regex rules only after suspicious signals.
8. Score and decide.
9. Log and block, or allow the application to continue.

The public `FireLine` class remains available for existing integrations, but internally delegates to `Fireline\Engine\WafEngine`.

Trusted proxy example:

```php
'trusted_proxies' => [
    '127.0.0.1',
    '10.0.0.0/8',
],
```

Leave `trusted_proxies` empty unless the site is behind a reverse proxy or load balancer you control.

## Rule Files

Rules are stored in [src/Compares](src/Compares):

- `sql.php`: SQL injection patterns
- `xss.php`: XSS and browser abuse patterns
- `query.php`: suspicious query string patterns
- `bots.php`: blocked user agents
- `ips.php`: blocked IPs or partial IP strings
- `ips_white_list.php`: allowed IPs and CIDR ranges when whitelist mode is enabled
- `ip_block_by_country.php`: country ISO codes blocked when country blocking is enabled

Most rule files contain regular expressions. Empty lines and lines beginning with `#` are ignored.

## Logging

Blocked requests are logged to:

```text
storage/logs/fireline.log
```

Logs are written as JSON lines. Each blocked request is one JSON object:

```json
{"level":"warn","event":"fireline.blocked_request","timestamp":"2026-05-13T12:00:00-04:00","unix_time":1778688000,"remote_addr":"203.0.113.10","method":"GET","route":"/products","request_uri":"/products?id=1","filter":"get","field":"get.id","score":30,"matched_score":30,"reason":"field_score_threshold","value":"1 union select password from users","normalized":"1 union select password from users","user_agent":"Mozilla/5.0","referer":"https://example.com/"}
```

Event fields:

- `level`: always `warn` for blocked requests.
- `event`: always `fireline.blocked_request`.
- `timestamp`: ISO-8601 timestamp.
- `unix_time`: Unix timestamp.
- `remote_addr`: `REMOTE_ADDR` from PHP.
- `method`: HTTP request method.
- `route`: parsed request path.
- `request_uri`: request URI from PHP.
- `filter`: field source that blocked the request, such as `get`, `post`, `cookie`, `header`, `json`, `raw`, `ip`, or `bot`.
- `field`: exact inspected field that crossed the threshold.
- `score`: total decision score.
- `matched_score`: score for the exact blocking field.
- `reason`: decision reason.
- `value`: matched value after sanitization and redaction.
- `normalized`: normalized matched value after sanitization and redaction.
- `user_agent`: user agent from PHP.
- `referer`: referer from PHP.

Attacker-controlled fields are sanitized before logging:

- Control characters are replaced with spaces.
- Common secret parameters such as `password`, `token`, `api_key`, `secret`, and `authorization` are redacted.
- Logged values are capped at 1000 characters.

If the log directory or file does not exist, Fireline attempts to create it. If the log file is not writable, Fireline throws an exception. Make sure `storage/logs` is writable by the PHP process.

## Profiling And Metrics

Fireline records lightweight in-process metrics for tuning rules and cache behavior.

```php
use Fireline\Telemetry\RuleMetrics;

$snapshot = RuleMetrics::snapshot();
```

The snapshot includes:

- `counters`: rule execution counts, rule match counts, false-positive counts, and cache writes.
- `timings`: scanner and regex timing data with `count`, `total_ms`, and `max_ms`.
- `cache_hit_ratios`: safe/threat cache hit ratios.
- `slowest_rules`: timing data sorted by slowest maximum execution time.

Examples:

```php
RuleMetrics::increment('rule.SQL_UNION_SELECT.executed');
RuleMetrics::timing('rule.SQL_UNION_SELECT', 0.14);
RuleMetrics::falsePositive('SQL_UNION_SELECT');
```

Current instrumentation tracks:

- Keyword scanner timing
- Keyword rule match counts
- Regex rule execution counts
- Regex rule match counts
- Regex rule timings
- Safe/threat cache hits and misses
- Safe/threat cache writes
- Manual false-positive counters

## CLI And Development Commands

Run tests:

```bash
php composer.phar test
```

Run the smoke test:

```bash
php composer.phar run smoke
```

Run PHP syntax checks:

```bash
php composer.phar run lint
```

The same commands work with global Composer:

```bash
composer test
composer run smoke
composer run lint
```

The legacy `fire.php` CLI currently exposes `help`. The cache commands shown in that help output are placeholders and are not currently registered.

## Troubleshooting

### Requests are not being blocked

- Confirm `auto_prepend_file` points to the copied web-root `fireline.php`.
- Confirm the web-root `fireline.php` includes the correct path to `fireline/index.php`.
- Confirm `bypass_firewall` is not set to `true`.
- Add a temporary test query such as `?q=javascript:alert(1)` and verify a `403 Forbidden` response.

### All traffic appears to come from the proxy

Set `trusted_proxies` to the IP or CIDR range of your reverse proxy. Fireline ignores `X-Forwarded-For` unless `REMOTE_ADDR` is trusted.

### Country blocking blocks unexpectedly

Country blocking fails closed if enabled and the GeoIP database is missing or unreadable. Confirm [src/GeoLite2-Country.mmdb](src/GeoLite2-Country.mmdb) exists and is readable.

### Logs are not written

- Confirm `storage/logs/fireline.log` exists.
- Confirm it is writable by the web server user.
- Confirm PHP has permission to write inside `storage/logs`.

### Composer install fails on old Composer

Use the checked-in Composer 2 PHAR:

```bash
php composer.phar install
```

Composer 1 is no longer supported by Packagist.
