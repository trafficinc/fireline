# Fire Line - Web Application Firewall (WAF) for PHP

> PHP based WAF (Web Application Firewall)

To install:

Download project, unzip, change name of folder to `fireline`, then copy `fireline` __folder__ above web root and copy fireline.php file in `config-webroot` folder to the web root of your web app, in `Laravel`,`CodeIgniter` it is in the `/public` folder. Also, make sure the `fireline` directory is one above the web root:

````
| fireline/
| public/
    |__ fireline.php
````

> Must also add `php_prepend` directive depending on your web server configuration:

* php ini: create or add to .htaccess or php.ini: 

`php_value auto_prepend_file "full_path_to_the_include_directory/fireline.php"`

* user ini: create and add or create a `.user.ini` file in web root, add line: 

`auto_prepend_file = fireline.php`

-----------------

In the `/fireline/src/Fireline.php` file:

Set Firewall configuration in `configs` array.

 ````
$current_request = [
            ...
            'configs' => [
                'bypass_firewall' => false,
                'strict_mode' => false,
                'ip_by_country' => false,
                'whitelist' => false,
            ],
        ]; 
````
__bypass_firewall__: will by-pass the firewall totally.

__strict_mode__: will activate the normalizer to catch more exploits.

__ip_by_country__: will block ips from countries via country ISO codes. (see `src/Compares/ip_block_by_country.php` for rules)

__whitelist__: will activate the whitelist (see `src/Compares/ips_white_list.php` for rules). When the "whitelist" is active, then the "blacklist" is inactive and when the "whitelist" is inactive, the "blacklist" is active. 

Other included filters: Bot blacklist, Query protection, SQL injection, and XSS. 

> Requirements: PHP: 7.1 or greater