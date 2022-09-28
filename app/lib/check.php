<?php
/**
 * The worker check actions.
 */

namespace check;

use db;
use thread;
use PDO;

/**
 * Calculates the number of emails that have to be processed.
 *
 * @param PDO $db the database connection.
 * @param bool $till whether to calculate metrics only for the remainder of the current hour.
 * @return array the different check metrics.
 */
function load(PDO $db, bool $till = false): array
{
    // Next subscription timestamp
    $startTs = db\scalar($db, 'SELECT u.validts FROM users u LEFT JOIN emails e ON e.email=u.email WHERE e.email IS NULL AND u.confirmed=1 ORDER BY u.validts ASC LIMIT 1');

    // Break if there are no emails to check
    if (!$startTs) {
        return [];
    }

    // Last timestamp: total, day and hour
    $timestamp = [
        'all'  => db\scalar($db, 'SELECT validts FROM users WHERE confirmed=1 ORDER BY validts DESC LIMIT 1'),
        // To be sent within next hour
        'hour' => $startTs + 60 * 60,
        // To be sent within next 12 hours (2 hours to spare)
        'day'  => $startTs + 60 * 60 * 10,
    ];

    // Number of emails: total, day and hour
    // We need just approximate number of unchecked emails
    $totals = [
        'all'  => db\scalar($db, "SELECT COUNT(*) FROM users WHERE confirmed=1 AND validts > :start LIMIT 1", ['start' => $startTs]),
        'day'  => db\scalar($db, "SELECT COUNT(*) FROM users WHERE confirmed=1 AND validts > :start AND validts < :end LIMIT 1", [
            'start' => $startTs,
            'end'   => $timestamp['day'],
        ]),
        'hour' => db\scalar($db, "SELECT COUNT(*) FROM users WHERE confirmed=1 AND validts > :start AND validts < :end LIMIT 1", [
            'start' => $startTs,
            'end'   => $timestamp['hour'],
        ]),
    ];

    // Number of emails per hour: total, day and hour
    $loads = [
        'all'  => ceil($totals['all'] / ceil(($timestamp['all'] - $startTs) / 60 / 60)),
        'day'  => ceil($totals['day'] / 10),
        'hour' => $totals['hour'],
    ];

    // Calculate the number of emails to be processed per hour to evenly distribute the load
    // Use maximum of total, next hour or today number of emails
    $loadType = array_search(max($loads), $loads);
    $batchQty = $loads[$loadType];
    $threadQty = ceil($batchQty / 60);

    // Reduce batch for the current hour
    if ($till) {
        $batchQty = ceil($batchQty * (3600 - time() % 3600) / 3600);
    }

    return [
        'totals' => $totals,
        'batch'  => $batchQty,
        'type'   => $loadType,
        'thread' => $threadQty,
    ];
}

/**
 * Adds emails that have to be checked into the queue.
 *
 * @param PDO $db the database connection.
 * @param bool $till whether to calculate metrics only for the remainder of the current hour.
 */
function queue(PDO $db, bool $till = false): void
{
    // Calculate load metrics
    $load = load($db, $till);

    // Break if there are no emails to check
    if (!$load) {
        echo "No emails to check.\n";
        return;
    }

    // Extract metrics
    ['totals' => $totals, 'batch' => $batchQty, 'type' => $loadType, 'thread' => $threadQty] = $load;

    // Check only emails to be sent within next 7 days
    $nextTs = time() + 60 * 60 * 24 * 7;

    echo "Total: {$totals['all']}. Day: {$totals['day']}. Hour: {$totals['hour']}. Batch: $batchQty ($loadType). Threads: $threadQty.\n";

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
