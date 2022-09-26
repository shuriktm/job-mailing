<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/func.php';
require __DIR__ . '/../lib/check.php';
require __DIR__ . '/../lib/send.php';

$config = require __DIR__ . '/../config.php';

$db = db\connection($config['db']);

$params = $_SERVER['argv'] ?? [];
array_shift($params);

$action = $params[0] ?? null;
switch ($action) {
    case 'check/queue':
        check\queue($db);
        break;
    case 'check':
        check\process($db);
        break;
    case 'send/queue':
        send\queue($db);
        break;
    case 'send':
        send\process($db, $config['mail']);
        break;
    default:
        echo implode("\n", [
            'Please choose an action (as the first argument):',
            '- check/queue (queue emails to check)',
            '- check (check emails)',
            '- send/queue (queue emails to send)',
            '- send (send emails)',
        ]);
        echo "\n";
}
