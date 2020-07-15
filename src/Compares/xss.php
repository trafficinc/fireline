<?php

/*
 * XSS rules
 * */

return [
    '(?:"[^"]*[^-]?>)|(?:[^\w\s]\s*\/>)|(?:>")',
    '(?:"+.*[<=]\s*"[^"]+")|(?:"\s*\w+\s*=)|(?:>\w=\/)|(?:#.+\)["\s]*>)|(?:"\s*(?:src|style|on\w+)\s*=\s*")|(?:[^"]?"[,;\s]+\w*[\[\(])',
    '(?:^>[\w\s]*<\/?\w{2,}>)',
    '(?:[+\/]\s*name[\W\d]*[)+])|(?:;\W*url\s*=)|(?:[^\w\s\/?:>]\s*(?:location|referrer|name)\s*[^\/\w\s-])',
    '(?:\W\s*hash\s*[^\w\s-])|(?:\w+=\W*[^,]*,[^\s(]\s*\()|(?:\?"[^\s"]":)|(?:(?<!\/)__[a-z]+__)|(?:(?:^|[\s)\]\}])(?:s|g)etter\s*=)',
    '(?:with\s*\(\s*.+\s*\)\s*\w+\s*\()|(?:(?:do|while|for)\s*\([^)]*\)\s*\{)|(?:\/[\w\s]*\[\W*\w)',
    '(?:[=(].+\?.+:)|(?:with\([^)]*\)\))|(?:\.\s*source\W)',
    '(?:\/\w*\s*\)\s*\()|(?:\([\w\s]+\([\w\s]+\)[\w\s]+\))|(?:(?<!(?:mozilla\/\d\.\d\s))\([^)[]+\[[^\]]+\][^)]*\))|(?:[^\s!][{([][^({[]+[{([][^}\])]+[}\])][\s+",\d]*[}\])])|(?:"\)?\]\W*\[)|(?:=\s*[^\s:;]+\s*[{([][^}\])]+[}\])];)',
    '(?:(?:\/|\\\\)?\.+(\/|\\\\)(?:\.+)?)|(?:\w+\.exe\??\s)|(?:;\s*\w+\s*\/[\w*-]+\/)|(?:\d\.\dx\|)|(?:%(?:c0\.|af\.|5c\.))|(?:\/(?:%2e){2})',
    '(?:%c0%ae\/)|(?:(?:\/|\\\\)(home|conf|usr|etc|proc|opt|s?bin|local|dev|tmp|kern|[br]oot|sys|system|windows|winnt|program|%[a-z_-]{3,}%)(?:\/|\\\\))|(?:(?:\/|\\\\)inetpub|localstart\.asp|boot\.ini)',
    '(?:etc\/\W*passwd)',
    '([^*\s\w,.\/?+-]\s*)?(?<![a-mo-z]\s)(?<![a-z\/_@])(\s*return\s*)?(?:alert|inputbox|showmod(?:al|eless)dialog|showhelp|infinity|isnan|isnull|iterator|msgbox|executeglobal|expression|prompt|write(?:ln)?|confirm|dialog|urn|(?:un)?eval|exec|execscript|tostring|status|execute|window|unescape|navigate|jquery|getscript|extend|prototype)(?(1)[^\w%"]|(?:\s*[^@\s\w%",.:\/+\-]))',
    '([^*:\s\w,.\/?+-]\s*)?(?<![a-z]\s)(?<![a-z\/_@])(\s*return\s*)?(?:hash|name|href|navigateandfind|source|pathname|close|constructor|port|protocol|assign|replace|back|forward|document|ownerdocument|window|top|this|self|parent|frames|_?content|date|cookie|innerhtml|innertext|csstext+?|outerhtml|print|moveby|resizeto|createstylesheet|stylesheets)(?(1)[^\w%"]|(?:\s*[^@\/\s\w%.+\-]))',
    '(?:,\s*(?:alert|showmodaldialog|eval)\s*,)|(?::\s*eval\s*[^\s])|([^:\s\w,.\/?+-]\s*)?(?<![a-z\/_@])(\s*return\s*)?(?:(?:document\s*\.)?(?:.+\/)?(?:alert|eval|msgbox|showmod(?:al|eless)dialog|showhelp|prompt|write(?:ln)?|confirm|dialog|open))\s*(?:[^.a-z\s\-]|(?:\s*[^\s\w,.@\/+-]))|(?:java[\s\/]*\.[\s\/]*lang)|(?:\w\s*=\s*new\s+\w+)|(?:&\s*\w+\s*\)[^,])|(?:\+[\W\d]*new\s+\w+[\W\d]*\+)|(?:document\.\w)',
    '(?:[".]script\s*\()|(?:\$\$?\s*\(\s*[\w"])|(?:\/[\w\s]+\/\.)|(?:=\s*\/\w+\/\s*\.)|(?:(?:this|window|top|parent|frames|self|content)\[\s*[(,"]*\s*[\w\$])|(?:,\s*new\s+\w+\s*[,;)])',
    '(?:[^:\s\w]+\s*[^\w\/](href|protocol|host|hostname|pathname|hash|port|cookie)[^\w])',
    '(?:firefoxurl:\w+\|)|(?:(?:file|res|telnet|nntp|news|mailto|chrome)\s*:\s*[%&#xu\/]+)|(wyciwyg|firefoxurl\s*:\s*\/\s*\/)',
    '(?:[^\w\s=]on(?!g\&gt;)\w+[^=_+-]*=[^$]+(?:\W|\&gt;)?)',
    '(?:\<[\/]?(?:[i]?frame|applet|isindex|marquee|keygen|script|audio|video|input|button|textarea|style|base|body|meta|link|object|embed|param|plaintext|xm\w+|image|im(?:g|port)))',
    '(?:\\x[01fe][\db-ce-f])|(?:%[01fe][\db-ce-f])|(?:&#[01fe][\db-ce-f])|(?:\\[01fe][\db-ce-f])|(?:&#x[01fe][\db-ce-f])',

];