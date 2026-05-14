<?php

return [
    '/login.php' => [
        'post.username' => [
            'type' => 'alnum',
            'max_length' => 64,
            'allowed_chars' => 'alnum',
            'denied_tokens' => ['union', 'select', 'sleep', 'script'],
        ],
        'post.password' => [
            'type' => 'opaque',
            'max_length' => 256,
        ],
    ],

    '/search.php' => [
        'get.q' => [
            'type' => 'text',
            'max_length' => 256,
            'allowed_chars' => 'free_text',
            'denied_tokens' => ['union', 'select', 'sleep', 'script'],
        ],
    ],

    '/echo.php' => [
        'get.msg' => [
            'type' => 'text',
            'max_length' => 512,
            'allowed_chars' => 'free_text',
            'denied_tokens' => ['script', 'javascript'],
        ],
        'post.comment' => [
            'type' => 'text',
            'max_length' => 1024,
            'allowed_chars' => 'free_text',
            'denied_tokens' => ['script', 'javascript'],
        ],
    ],

    '/api.php' => [
        'get.item' => [
            'type' => 'int',
            'max_length' => 16,
            'allowed_chars' => 'alnum',
        ],
        'get.file' => [
            'type' => 'slug',
            'max_length' => 128,
            'allowed_chars' => 'slug',
            'denied_tokens' => ['etc', 'passwd'],
        ],
    ],

    '/upload.php' => [
        'file.file.name' => [
            'type' => 'text',
            'max_length' => 180,
            'denied_tokens' => ['php', 'phtml', 'phar'],
        ],
        'file.file.type' => [
            'type' => 'text',
            'max_length' => 120,
            'denied_tokens' => ['php'],
        ],
    ],
];
