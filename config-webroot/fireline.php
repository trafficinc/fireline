<?php
/*
 * Web Root Init file, Goes into web root
 * May have to adjust paths to fit your application.
 * */
include __DIR__ . '/fireline/index.php';
$waf = new FireLine();
$waf->run();