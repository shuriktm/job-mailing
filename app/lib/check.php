<?php
/**
 * The worker check actions.
 */

namespace check;

use db;
use thread;
use PDO;

/**
 * Adds emails that have to be checked into the queue.
 *
 * @param PDO $db the database connection.
 */
function queue(PDO $db): void
{
    // Next subscription timestamp
    $firstTs = db\scalar($db, 'SELECT u.validts FROM users u LEFT JOIN emails e ON e.email=u.email WHERE e.email IS NULL AND u.confirmed=1 ORDER BY u.validts ASC LIMIT 1');

    // We need just approximate number of unchecked emails
    $confirmedQty = db\scalar($db, "SELECT COUNT(*) FROM users WHERE confirmed=1 AND validts > $firstTs LIMIT 1");

    // Last subscription timestamp
    $lastTs = db\scalar($db, 'SELECT validts FROM users WHERE confirmed=1 ORDER BY validts DESC LIMIT 1');

    // Break if there are no emails to check
    if (!$confirmedQty) {
        echo "No emails to check.\n";
        return;
    }

    // Calculate the number of emails to be processed per hour to evenly distribute the load
    $hours = ceil(($lastTs - $firstTs) / 60 / 60);
    $batchQty = ceil($confirmedQty / $hours);
    $threadQty = ceil($batchQty / 60);

    // Check only emails to be sent within next 7 days
    $nextTs = time() + 60 * 60 * 24 * 7;

    echo "Total: $confirmedQty. Batch: $batchQty. Threads: $threadQty.\n";

    // Retrieve a batch of emails to process within next hour
    // TODO: Include all emails that have to be sent in next 4 days
    $emails = db\column($db, "SELECT u.email FROM users u LEFT JOIN emails e ON e.email=u.email WHERE e.email IS NULL AND u.confirmed=1 AND u.validts < $nextTs ORDER BY u.validts ASC LIMIT $batchQty");

    // Add items to emails table
    db\upsert($db, 'emails', 'email', array_map(fn($email) => ['email' => $email], $emails));

    $qty = count($emails);
    echo "Selected $qty email(s) for check.\n";

    // Divide emails into chunks to check a small chunk of them every minute, schedule checks
    $queueTs = time();
    foreach (array_chunk($emails, $threadQty) as $chunk) {
        db\upsert($db, 'queue_check', 'email', array_map(fn($email) => [
            'email'   => $email,
            'queuets' => $queueTs,
        ], $chunk));

        $qty = count($chunk);
        echo "Added $qty email(s) into the queue.\n";

        $queueTs += 60;
    }
}

/**
 * Checks emails from the queue.
 *
 * @param array $config the thread config.
 * @param PDO $db the database connection.
 */
function process(PDO $db, array $config): void
{
    $nowTs = time();
    $retryTs = $nowTs - 60 * 60;

    // Maximum number of threads
    $limit = $config['max'];

    // Retrieve a small chunk of emails (maximum 20) from the queue, also retry emails that have not been checked due to any unexpected error
    $emails = db\column($db, "SELECT email FROM queue_check WHERE (processts IS NULL OR processts < $retryTs) AND queuets < $nowTs LIMIT $limit");

    // Break if there are no emails to check
    if (!$emails) {
        echo "No emails in the queue.\n";
        return;
    }

    // Lock emails in the queue by setting a process timestamp
    db\upsert($db, 'queue_check', 'email', array_map(fn($email) => [
        'email'     => $email,
        'processts' => $nowTs,
    ], $emails));

    $qty = count($emails);
    echo "Locked $qty email(s) for processing.\n";

    // Check emails in parallel threads
    thread\copy(params: array_map(fn($email) => ['check/one', $email], $emails));

    $time = time() - $nowTs;
    echo "Checked $qty email(s).\n";
    echo "Time: $time second(s).\n";
}

/**
 * Checks the specified email.
 *
 * @param PDO $db the database connection.
 * @param string $email the user email.
 */
function one(PDO $db, string $email): void
{
    // Process check
    echo "Check email: $email...\r";
    $result = check_email($email);
    $status = $result ? 'valid' : 'invalid';
    echo "Email $email is $status.\n";

    // Save check result
    db\upsert($db, 'emails', 'email', [
        ['email' => $email, 'checked' => true, 'valid' => $result],
    ]);

    // Delete email from the queue
    db\delete($db, 'queue_check', 'email', [$email]);
}
