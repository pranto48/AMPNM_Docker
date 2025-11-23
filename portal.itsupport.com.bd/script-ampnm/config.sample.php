<?php
return [
    'app_name' => 'AMPNM PHP',
    'app_url' => '/script-ampnm',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'ampnm',
        'user' => 'ampnm_user',
        'password' => 'secret',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'smtp-user',
        'password' => 'smtp-password',
        'encryption' => 'tls',
        'from_email' => 'no-reply@example.com',
        'from_name' => 'AMPNM Alerts',
    ],
    'license' => [
        'status' => 'missing',
    ],
    'setup_complete' => false,
];
