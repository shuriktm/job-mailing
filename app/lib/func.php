<?php
/**
 * The dummy functions to check and send emails.
 */

/**
 * Checks an email and return the validation result.
 *
 * @param string $email the user email.
 * @return bool whether the email is valid.
 */
function check_email(string $email): bool
{
    sleep(rand(1, 60));

    return (bool) rand(0, 1);
}

/**
 * Sends an email to the user notifying them of an upcoming subscription renewal.
 *
 * @param string $email the user email.
 * @param string $from the sender email.
 * @param string $to the recipient email.
 * @param string $subj the subject text.
 * @param string $body the email body text.
 * @return bool whether the email has been successfully sent.
 */
function send_email(string $email, string $from, string $to, string $subj, string $body): bool
{
    sleep(rand(1, 10));

    return true;
}
