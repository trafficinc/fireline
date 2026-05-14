<?php

define('APP_NAME', 'Fireline');
define('APP_VERSION', 'v1.0.0');
define('DEBUG', false);

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

    function get_referer(): string{
        if (get_env('HTTP_REFERER'))
            return get_env('HTTP_REFERER');
        return 'no referer';
    }

    function get_ip(): string {
		if (get_env('HTTP_X_FORWARDED_FOR')) {
			return get_env('HTTP_X_FORWARDED_FOR');
		} elseif (get_env('HTTP_CLIENT_IP')) {
			return get_env('HTTP_CLIENT_IP');
		} else {
			return get_env('REMOTE_ADDR');
		}
    }

    function get_user_agent(): string {
		if(get_env('HTTP_USER_AGENT'))
			return get_env('HTTP_USER_AGENT');
		return 'none';
    }

    function get_query_string(): string {
		if(get_env('QUERY_STRING'))
			return str_replace('%09', '%20', get_env('QUERY_STRING'));
		return '';
    }

    function get_request_method(): string {
		if(get_env('REQUEST_METHOD'))
			return get_env('REQUEST_METHOD');
		return 'none';
	}