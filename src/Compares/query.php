<?php

/*
 * QUERY rules
 * */

return [
    '(\bunion\b.{0,80}\bselect\b|wget|window(.*)open|\$\_(.*)|kill\W|ftp\W|fwrite|fopen|file\W|\Wexe|document\.(cookie|location))',
    '((.|\W)getenv(.|\W)|grep|http_(.|php|user_agent|host)|javascript\W|\.jsp(\W|$)|(.|\/)bin\/(.|echo|kill|ps|python|tclsh|nasm|mail)|\$_\w\w(.*)|phpinfo\W\W|<\?php|passwd(.|\W))',
    '(\bm(?:cd|dir)\W|\brm(?:dir)?\W)',
    '(\Wcat\W|\Wetc\W|\Wpasswd\W|\/shell\.[a-z]|which(.*)[a-z])',
];
