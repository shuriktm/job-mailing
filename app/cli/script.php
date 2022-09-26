<?php

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/thread.php';
require_once __DIR__ . '/../lib/func.php';
require_once __DIR__ . '/../lib/check.php';
require_once __DIR__ . '/../lib/send.php';

$config = require __DIR__ . '/../config.php';

$db = db\connection($config['db']);

$params = $_SERVER['argv'] ?? [];
array_shift($params);

$action = $params[0] ?? null;
switch ($action) {
    case 'check/queue':
        check\queue($db);
        break;
    case 'check/process':
        check\process($db, config: $config['thread']['check']);
        break;
    case 'check/one':
        check\one($db, email: $params[1] ?? null);
        break;
    case 'send/queue':
        send\queue($db);
        break;
    case 'send/process':
        send\process($db, config: $config['thread']['send']);
        break;
    case 'send/one':
        send\one($db, config: $config['mail'], email: $params[1] ?? null, username: $params[2] ?? null);
        break;
    default:
        echo implode("\n", [
            'Please choose an action (as the first argument):',
            '- check/queue # queue emails to check',
            '- check/process # check emails from the queue',
            '- check/one [email] # check single email',
            '- send/queue # queue emails to send',
            '- send/process # send emails from the queue',
            '- send/one [email] [username] # send single email)',
        ]);
        echo "\n";
}
