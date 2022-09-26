<?php

return [
    'db'   => [
        'dsn'      => 'mysql:host=' . getenv("MYSQL_HOST") . ';dbname=' . getenv("MYSQL_DATABASE"),
        'username' => getenv("MYSQL_USER"),
        'password' => getenv("MYSQL_PASSWORD"),
    ],
    'mail' => [
        'from' => 'admin.mailing@example.com',
    ],
];
