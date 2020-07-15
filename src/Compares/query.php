<?php

/*
 * QUERY rules
 * */

return [
    '(union|wget|window(.*)open|\$\_(.*)|kill\W|ftp\W|fwrite|fopen|file\W|\Wexe|document.(cookie|location))',
    '((.|\W)getenv(.|\W)|grep|http_(.|php|user_agent|host)|javascript\W|.js|.jsp|(.|\/)bin\/(.|echo|kill|ps|python|tclsh|nasm|mail)|txt|\$_\w\w(.*)|phpinfo\W\W|<\?php|passwd(.|\W))',
    '(m(cd\W|dir\W)|rm(.|dir))',
    '(\Wcat\W|\Wetc\W|\Wpasswd\W|\/shell.[a-z]|which(.*)[a-z])',
];