<?php

namespace Fireline\Rules;

class Categories
{
    const SQLI = 'sqli';
    const XSS = 'xss';
    const RCE = 'rce';
    const SHELL = 'shell';
    const LFI = 'lfi';
    const RFI = 'rfi';
    const WEB_SHELL = 'webshell';
    const SCANNER = 'scanner';
    const ENCODING = 'encoding';
    const PROTOCOL = 'protocol';
    const PHP_INJECTION = 'php_injection';
    const UPLOAD = 'upload';
}
