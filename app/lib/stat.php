<?php
/**
 * The helper functions to show statistics.
 */

namespace stat;

use db;
use check;
use send;
use PDO;

/**
 * Fetches statistics for web dashboard.
 *
 * @param PDO $db the database connection.
 * @return array the statistics data.
 */
function all(PDO $db): array
{
    // Check metrics
    $check = check\load($db);

    // Sending metrics
    $send = send\load($db);

    return [
        [
            'name'  => 'General',
            'text'  => 'The database contains',
            'items' => [
                [
                    'label'   => 'Users',
                    'caption' => 'All users in the database',
                    'qty'     => number_format(db\scalar($db, 'SELECT COUNT(*) FROM users'), thousands_separator: ' '),
                ],
                [
                    'label'   => 'Confirmed',
                    'caption' => 'Users with confirmed email',
                    'qty'     => number_format(db\scalar($db, 'SELECT COUNT(*) FROM users WHERE confirmed=1'), thousands_separator: ' '),
                ],
                [
                    'label'   => 'Valid',
                    'caption' => 'Number of checked and valid emails',
                    'qty'     => number_format(db\scalar($db, 'SELECT COUNT(*) FROM emails WHERE valid=1'), thousands_separator: ' '),
                ],
                [
                    'label'   => 'Notified',
                    'caption' => 'Users that have been already notified',
                    'qty'     => number_format(db\scalar($db, 'SELECT COUNT(*) FROM users WHERE notified=1'), thousands_separator: ' '),
                ],
            ],
        ],
        [
            'name'  => 'Check',
            'text'  => 'Emails are automatically checked using cron job',
            'color' => 'primary',
            'items' => [
                [
                    'label'   => 'Remain',
                    'caption' => 'All emails to be checked',
                    'qty'     => number_format($check['totals']['all'] ?? 0, thousands_separator: ' '),
                ],
                [
                    'label'   => 'Queue',
                    'caption' => 'Emails in the queue',
                    'qty'     => number_format(db\scalar($db, 'SELECT COUNT(*) FROM queue_check'), thousands_separator: ' '),
                ],
                [
                    'label'   => 'Batch',
                    'caption' => 'Emails to be checked per hour',
                    'qty'     => number_format($check['batch'] ?? 0, thousands_separator: ' '),
                ],
                [
                    'label'   => 'Threads',
                    'caption' => 'Number of parallel checks',
                    'qty'     => number_format($check['thread'] ?? 0, thousands_separator: ' '),
                ],
            ],
        ],
        [
            'name'  => 'Mailing',
            'text'  => 'Emails are automatically sent 3 days before expiry date',
            'color' => 'success',
            'items' => [
                [
                    'label'   => 'Today',
                    'caption' => 'Number of emails to send within a day',
                    'qty'     => number_format($send['day'] ?? 0, thousands_separator: ' '),
                ],
                [
                    'label'   => 'Queue',
                    'caption' => 'Emails in the queue',
                    'qty'     => number_format(db\scalar($db, 'SELECT COUNT(*) FROM queue_send'), thousands_separator: ' '),
                ],
                [
                    'label'   => 'Batch',
                    'caption' => 'Emails to be sent per hour',
                    'qty'     => number_format($send['batch'] ?? 0, thousands_separator: ' '),
                ],
                [
                    'label'   => 'Threads',
                    'caption' => 'Number of parallel processes',
                    'qty'     => number_format($send['thread'] ?? 0, thousands_separator: ' '),
                ],
            ],
        ],
    ];
}
