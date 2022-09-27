<?php

return [
    'db'     => [
        'dsn'      => 'mysql:host=' . getenv("MYSQL_HOST") . ';dbname=' . getenv("MYSQL_DATABASE"),
        'username' => getenv("MYSQL_USER"),
        'password' => getenv("MYSQL_PASSWORD"),
    ],
    'mail'   => [
        'from' => getenv('SEND_FROM_EMAIL') ? : 'mailing@example.com',
    ],
    'thread' => [
        'check' => ['max' => getenv('CHECK_THREAD_MAX') ? : 100],
        'send'  => ['max' => getenv('SEND_THREAD_MAX') ? : 20],
    ],
];
