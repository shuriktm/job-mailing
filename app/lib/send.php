<?php
/**
 * The worker send actions.
 */

namespace send;

use db;
use PDO;

/**
 * Adds emails for notification into the queue.
 *
 * @param PDO $db the database connection.
 */
function queue(PDO $db): void
{
    // Number of emails to be sent within next 12 hours
    $todayTs = time() + 60 * 60 * 24 * 3.5;

    // We do not check if emails is already in the queue, it's not important - anyway we need to retry it. But additional join can increase fetching time.
    $validQty = db\scalar($db, "SELECT COUNT(*) FROM users u LEFT JOIN emails e ON e.email=u.email WHERE u.confirmed=1 AND u.notified=0 AND u.validts < $todayTs AND e.valid=1 ORDER BY u.validts ASC LIMIT 1");

    // Break if there are no emails to send
    if (!$validQty) {
        return;
    }

    // Calculate the number of emails to be processed per hour to evenly distribute the load
    $batchQty = ceil($validQty / 12);
    $chunkQty = ceil($batchQty / 60);
    $threadQty = ceil($chunkQty / 5);

    echo "Total: $validQty. Batch: $batchQty. Threads: $threadQty.\n";

    // Retrieve a batch of emails to process within next hour
    $emails = db\column($db, "SELECT u.email FROM users u LEFT JOIN emails e ON e.email=u.email WHERE u.confirmed=1 AND u.notified=0 AND u.validts < $todayTs AND e.valid=1 ORDER BY u.validts ASC LIMIT $batchQty");

    $qty = count($emails);
    echo "Selected $qty email(s) for sending.\n";

    // Divide emails into chunks to check a small chunk of them every minute, schedule sending
    $queueTs = time();
    foreach (array_chunk($emails, $chunkQty) as $chunk) {
        db\upsert($db, 'queue_send', 'email', array_map(fn($email) => [
            'email'   => $email,
            'queuets' => $queueTs,
        ], $chunk));

        $qty = count($chunk);
        echo "Added $qty email(s) into the queue.\n";

        $queueTs += 60;
    }
}

/**
 * Sends notification to emails from the queue.
 *
 * @param PDO $db the database connection.
 * @param array $config the mailing config.
 */
function process(PDO $db, array $config): void
{
    $nowTs = time();
    $retryTs = $nowTs - 60 * 60;

    // Retrieve a small chunk of emails from the queue, also retry emails that have not been checked due to any unexpected error
    $data = db\all($db, "SELECT q.email, u.username FROM queue_send q, users u WHERE (q.processts IS NULL OR q.processts < $retryTs) AND q.queuets < $nowTs AND u.email=q.email");
    $emails = array_map(fn($item) => $item['email'], $data);

    // Lock emails in the queue by setting a process timestamp
    db\upsert($db, 'queue_send', 'email', array_map(fn($email) => [
        'email'     => $email,
        'processts' => $nowTs,
    ], $emails));

    $qty = count($emails);
    echo "Locked $qty email(s) for processing.\n";

    // Send emails in parallel threads
    // TODO: Use threads
    $sent = [];
    foreach ($data as $item) {
        $email = $item['email'];
        $message = strtr('{username}, your subscription is expiring soon', ['{username}' => $item['username']]);

        echo "Send email: $email...\r";
        $result = send_email($email, from: $config['from'], to: $email, subj: 'Subscription Renewal', body: $message);
        $sent[$email] = [
            'email'    => $email,
            'notified' => $result,
        ];
        echo "Send email: $email... Done.\n";
    }

    // Save mailing results
    db\upsert($db, 'users', 'email', $sent);

    $qty = count($sent);
    echo "Sent $qty email(s).\n";

    // Delete emails from the queue
    db\delete($db, 'queue_send', 'email', array_keys($sent));
    echo "Deleted $qty email(s) from the queue.\n";
}

