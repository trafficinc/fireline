<?php

defined('APP_NAME') || define('APP_NAME', 'Fireline');
defined('APP_VERSION') || define('APP_VERSION', 'v1.1.0');
defined('DEBUG') || define('DEBUG', false);

if (!function_exists('get_env')) {
    function get_env($st_var): string {
		global $HTTP_SERVER_VARS;
		if(isset($_SERVER[$st_var])) {
			return strip_tags( $_SERVER[$st_var] );
		} elseif(isset($_ENV[$st_var])) {
			return strip_tags( $_ENV[$st_var] );
		} elseif(isset($HTTP_SERVER_VARS[$st_var])) {
			return strip_tags( $HTTP_SERVER_VARS[$st_var] );
		} elseif(getenv($st_var)) {
			return strip_tags(getenv($st_var));
		} elseif(function_exists('apache_getenv') && apache_getenv($st_var, true)) {
			return strip_tags(apache_getenv($st_var, true));
		}
		return '';
    }
}

if (!function_exists('fireline_ip_in_cidr')) {
    function fireline_ip_in_cidr(string $ip, string $cidr): bool {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

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
}

if (!function_exists('fireline_trusted_proxy')) {
    function fireline_trusted_proxy(string $remoteAddr, array $trustedProxies): bool {
        foreach ($trustedProxies as $proxy) {
            if (fireline_ip_in_cidr($remoteAddr, trim((string) $proxy))) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('get_referer')) {
    function get_referer(): string{
        if (get_env('HTTP_REFERER'))
            return get_env('HTTP_REFERER');
        return 'no referer';
    }
}

if (!function_exists('get_ip')) {
    function get_ip(array $trustedProxies = []): string {
        $remoteAddr = get_env('REMOTE_ADDR');
        if ($remoteAddr === '') {
            return '';
        }

        if (!fireline_trusted_proxy($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }

        $forwardedFor = get_env('HTTP_X_FORWARDED_FOR');
        if ($forwardedFor !== '') {
            $forwardedIps = array_map('trim', explode(',', $forwardedFor));
            foreach ($forwardedIps as $forwardedIp) {
                if (filter_var($forwardedIp, FILTER_VALIDATE_IP)) {
                    return $forwardedIp;
                }
            }
        }

        $clientIp = get_env('HTTP_CLIENT_IP');
        if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return $clientIp;
        }

        return $remoteAddr;
    }
}

if (!function_exists('get_user_agent')) {
    function get_user_agent(): string {
		if(get_env('HTTP_USER_AGENT'))
			return get_env('HTTP_USER_AGENT');
		return 'none';
    }
}

if (!function_exists('get_query_string')) {
    function get_query_string(): string {
		if(get_env('QUERY_STRING'))
			return str_replace('%09', '%20', get_env('QUERY_STRING'));
		return '';
    }
}

if (!function_exists('get_request_method')) {
    function get_request_method(): string {
		if(get_env('REQUEST_METHOD'))
			return get_env('REQUEST_METHOD');
		return 'none';
	}
}
