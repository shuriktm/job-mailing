<?php

require __DIR__ . '/../lib/func.php';
require __DIR__ . '/../lib/db.php';

$config = require __DIR__ . '/../config.php';

$db = db\connection($config['db']);

$params = $_SERVER['argv'] ?? [];
array_shift($params);

$action = $params[0] ?? null;
switch ($action) {
    case 'check/queue':
        // TODO: Schedule emails to check

        $emails = db\find($db, 'SELECT * FROM users WHERE confirmed=:confirmed LIMIT 1', ['confirmed' => true]);
        var_dump($emails);

        $updated = db\upsert($db, 'emails', 'email', [
            ['email' => 'd4d904fdfa-380471@example.com', 'valid' => 1, 'checked' => 1],
            ['email' => '3c66156ce4-84353@example.com', 'valid' => 0, 'checked' => 1],
        ]);
        var_dump($updated);

        $deleted = db\delete($db, 'emails', 'email', [
            'd4d904fdfa-380471@example.com',
            '3c66156ce4-84353@example.com',
        ]);
        var_dump($deleted);
        break;
    case 'check/do':
        // TODO: Check emails from the queue

        echo "Check email...\n";
        var_dump(check_email('test@example.email'));
        break;
    case 'send/queue':
        // TODO: Schedule emails to send
        break;
    case 'send/do':
        // TODO: Send emails from the queue

        echo "Send email...\n";
        $message = strtr('{username}, your subscription is expiring soon', ['{username}' => 'test']);
        var_dump(send_email('test@example.email', 'test@example.email', 'test@example.email', 'Subscription Renewal', $message));
        break;
    default:
        echo implode("\n", [
            'Please choose an action (as the first argument):',
            '- check/queue (queue emails to check)',
            '- check/process (check emails)',
            '- send/queue (queue emails to send)',
            '- send/process (send emails)',
        ]);
        echo "\n";
}
