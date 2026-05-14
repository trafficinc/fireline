<?php

namespace Fireline\Engine;

if (!class_exists('GeoIp2\\Database\\Reader')) {
    $geoIpPhar = dirname(__DIR__) . '/geoip2.phar';
    if (is_readable($geoIpPhar)) {
        require_once $geoIpPhar;
    }
}

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;

class IpGuard
{
    protected $blockedIps;
    protected $whitelist;
    protected $blockedCountries;

    public function __construct(?array $blockedIps = null, ?array $whitelist = null, ?array $blockedCountries = null)
    {
        $root = dirname(__DIR__, 2) . '/src/Compares/';
        $this->blockedIps = $blockedIps ?? $this->loadArray($root . 'ips.php');
        $this->whitelist = $whitelist ?? $this->loadArray($root . 'ips_white_list.php');
        $this->blockedCountries = $blockedCountries ?? $this->loadArray($root . 'ip_block_by_country.php');
    }

    public function safe(string $ip, array $config): bool
    {
        if (($config['ip_by_country'] ?? false) && !$this->countrySafe($ip)) {
            return false;
        }

        if ($config['whitelist'] ?? false) {
            return $this->inList($ip, $this->whitelist, false);
        }

        return !$this->inList($ip, $this->blockedIps, true);
    }

    protected function countrySafe(string $ip): bool
    {
        $geoDb = dirname(__DIR__) . '/GeoLite2-Country.mmdb';
        if (!is_readable($geoDb)) {
            return false;
        }

        try {
            $record = (new Reader($geoDb))->country($ip);
        } catch (AddressNotFoundException $e) {
            return true;
        }

        return !in_array($record->country->isoCode, $this->blockedCountries, true);
    }

    protected function inList(string $ip, array $list, bool $allowPartial): bool
    {
        foreach ($list as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '' || strpos($entry, '#') === 0) {
                continue;
            }

            if (strpos($entry, '/') !== false && $this->cidr($ip, $entry)) {
                return true;
            }

            if ($ip === $entry || ($allowPartial && stripos($ip, $entry) !== false)) {
                return true;
            }
        }

        return false;
    }

    protected function cidr(string $ip, string $cidr): bool
    {
        list($net, $mask) = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $netLong = ip2long($net);
        $mask = (int) $mask;

        if ($ipLong === false || $netLong === false || $mask < 0 || $mask > 32) {
            return false;
        }

        $maskLong = -1 << (32 - $mask);
        return ($ipLong & $maskLong) === ($netLong & $maskLong);
    }

    protected function loadArray(string $file): array
    {
        $loaded = is_readable($file) ? require $file : [];
        return is_array($loaded) ? $loaded : [];
    }
}
