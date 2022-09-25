<?php
/**
 * The worker check actions.
 */

namespace check;

use db;
use PDO;

/**
 * Adds emails that have to be checked into the queue.
 *
 * @param PDO $db the database connection.
 */
function queue(PDO $db): void
{
    // Next subscription timestamp
    $first = db\scalar($db, 'SELECT u.validts FROM users u LEFT JOIN emails e ON e.email=u.email WHERE e.email IS NULL AND u.confirmed=1 ORDER BY u.validts ASC LIMIT 1');

    // We need just approximate number of unchecked emails
    $confirmed = db\scalar($db, "SELECT COUNT(*) FROM users WHERE confirmed=1 AND validts > $first LIMIT 1");

    // Last subscription timestamp
    $last = db\scalar($db, 'SELECT validts FROM users WHERE confirmed=1 ORDER BY validts DESC LIMIT 1');

    // Calculate the number of emails to be processed per hour to evenly distribute the load
    $hours = round(($last - $first) / 60 / 60);
    $batch = round($confirmed / $hours);
    $threads = round($batch / 60);

    // Check only emails to be sent within next 7 days
    $next = time() + 60 * 60 * 24 * 7;

    echo "Total: $confirmed. Batch: $batch. Threads: $threads.\n";

    // Retrieve a batch of emails to process within next hour
    $emails = db\column($db, "SELECT u.email FROM users u LEFT JOIN emails e ON e.email=u.email WHERE e.email IS NULL AND u.confirmed=1 AND u.validts < $next ORDER BY u.validts ASC LIMIT $batch");

    // Add items to emails table
    $num = db\upsert($db, 'emails', 'email', array_map(fn($email) => ['email' => $email], $emails));
    echo "Selected $num emails for check.\n";

    // Divide emails into chunks to check a small chunk of them every minute, schedule checks
    $queueTs = time();
    foreach (array_chunk($emails, $threads) as $chunk) {
        $num = db\upsert($db, 'queue_check', 'email', array_map(fn($email) => [
            'email'   => $email,
            'queuets' => $queueTs,
        ], $chunk));
        echo "Added $num emails into the queue.\n";
        $queueTs += 60;
    }
}

/**
 * Checks emails from the queue.
 *
 * @param PDO $db the database connection.
 */
function process(PDO $db): void
{
    $now = time();
    $retry = $now - 60 * 60;

    // Retrieve a small chunk of emails (maximum 20) from the queue, also retry emails that have not been checked due to any unexpected error
    $emails = db\column($db, "SELECT email FROM queue_check WHERE (processts IS NULL OR processts < $retry) AND queuets < $now LIMIT 20");

    // Lock emails in the queue by setting a process timestamp
    $num = db\upsert($db, 'queue_check', 'email', array_map(fn($email) => [
        'email'     => $email,
        'processts' => $now,
    ], $emails));
    echo "Locked $num emails for processing.\n";

    // Check emails in parallel threads
    // TODO: Use threads
    $checked = [];
    foreach ($emails as $email) {
        echo "Check email: $email...\r";
        $result = check_email($email);
        $checked[$email] = [
            'email'   => $email,
            'checked' => true,
            'valid'   => $result,
        ];
        echo "Check email: $email... Done.\n";
    }

    // Save check results
    $num = db\upsert($db, 'emails', 'email', $checked);
    echo "Checked $num emails.\n";

    // Delete emails from the queue
    $num = db\delete($db, 'queue_check', 'email', array_keys($checked));
    echo "Deleted $num emails from the queue.\n";
}
