<?php
/**
 * SMTP configuration for sending real emails (borrow confirmations, due-date reminders).
 *
 * Pre-filled with Doms Library's sending account, so this doesn't need to be
 * re-edited every time the rest of the code changes.
 *
 * For Gmail: this uses an "App Password", not the normal Gmail password.
 * Generate one at: https://myaccount.google.com/apppasswords
 * (requires 2-Step Verification to be turned on for the Google account first)
 */
return [
    'enabled'    => true,
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',          // 'tls' or 'ssl'
    'username'   => 'domslibrary7@gmail.com',
    'password'   => 'qhmv cwej ldhq lunk',
    'from_email' => 'domslibrary7@gmail.com',
    'from_name'  => 'Doms Library',
];
