<?php
/**
 * The worker send actions.
 */

namespace send;

use db;
use thread;
use PDO;

/**
 * Calculates the number of emails that have to be processed.
 *
 * @param PDO $db the database connection.
 * @return array the different check metrics.
 */
function load(PDO $db): array
{
    // Number of emails to be sent within next 12 hours
    $todayTs = time() + 60 * 60 * 24 * 3.5;

    // We do not check if emails is already in the queue, it's not important - anyway we need to retry it. But additional join can increase fetching time.
    $dayQty = db\scalar($db, "SELECT COUNT(*) FROM users u LEFT JOIN emails e ON e.email=u.email WHERE u.confirmed=1 AND u.notified=0 AND u.validts < $todayTs AND e.valid=1 ORDER BY u.validts ASC LIMIT 1");

    // Break if there are no emails to send
    if (!$dayQty) {
        return [];
    }

    // Calculate the number of emails to be processed per hour to evenly distribute the load
    $batchQty = ceil($dayQty / 12);
    $chunkQty = ceil($batchQty / 60);
    $threadQty = ceil($chunkQty / 5);

    return [
        'today'  => $todayTs,
        'day'    => $dayQty,
        'batch'  => $batchQty,
        'chunk'  => $chunkQty,
        'thread' => $threadQty,
    ];
}

/**
 * Adds emails for notification into the queue.
 *
 * @param PDO $db the database connection.
 */
function queue(PDO $db): void
{
    // Calculate load metrics
    $load = load($db);

    // Break if there are no emails to send
    if (!$load) {
        echo "No emails to send.\n";
        return;
    }

    // Extract metrics
    ['today' => $todayTs, 'day' => $dayQty, 'batch' => $batchQty, 'chunk' => $chunkQty, 'thread' => $threadQty] = $load;

    echo "Day: $dayQty. Batch: $batchQty. Threads: $threadQty.\n";

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
 * @param array $config the thread config.
 * @param PDO $db the database connection.
 */
function process(PDO $db, array $config): void
{
    $nowTs = time();
    $retryTs = $nowTs - 60 * 60;

    // Maximum number of threads
    $limit = $config['max'] * 5;

    // Retrieve a small chunk of emails from the queue, also retry emails that have not been checked due to any unexpected error
    $data = db\all($db, "SELECT q.email, u.username FROM queue_send q, users u WHERE (q.processts IS NULL OR q.processts < $retryTs) AND q.queuets < $nowTs AND u.email=q.email LIMIT $limit");
    $emails = array_map(fn($item) => $item['email'], $data);

    // Break if there are no emails to send
    if (!$emails) {
        echo "No emails in the queue.\n";
        return;
    }

    // Lock emails in the queue by setting a process timestamp
    db\upsert($db, 'queue_send', 'email', array_map(fn($email) => [
        'email'     => $email,
        'processts' => $nowTs,
    ], $emails));

    $qty = count($emails);
    echo "Locked $qty email(s) for processing.\n";

    // Send emails in parallel threads
    // Each thread works maximum 10 seconds, so split emails into 5 chunks to evenly distribute the load during 1 minute
    foreach (array_chunk($data, ceil($qty / 5)) as $group) {
        thread\copy(params: array_map(fn($item) => ['send/one', $item['email'], $item['username']], $group));
    }

    $time = time() - $nowTs;
    echo "Sent $qty email(s).\n";
    echo "Time: $time second(s).\n";
}

/**
 * Checks the specified email.
 *
 * @param PDO $db the database connection.
 * @param array $config the mailing config.
 * @param string $email the user email.
 * @param string $username the username.
 */
function one(PDO $db, array $config, string $email, string $username): void
{
    // Prepare message
    $message = strtr('{username}, your subscription is expiring soon', ['{username}' => $username]);

    // Send email
    echo "Send email to $email...\r";
    $result = send_email($email, from: $config['from'], to: $email, subj: 'Subscription Renewal', body: $message);
    $status = $result ? 'has been sent' : 'cannot be sent';
    echo "Email to $email $status.\n";

    // Mark user as notified
    db\upsert($db, 'users', 'email', [
        ['email' => $email, 'notified' => $result],
    ]);

    // Delete email from the queue
    db\delete($db, 'queue_send', 'email', [$email]);
}

