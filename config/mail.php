<?php
/**
 * SMTP configuration for sending real emails (borrow confirmations, due-date reminders).
 *
 * Fill these in with real values before mail will actually send anywhere.
 * For Gmail: you MUST use an "App Password", not your normal Gmail password.
 * Generate one at: https://myaccount.google.com/apppasswords
 * (requires 2-Step Verification to be turned on for the Google account first)
 */
return [
    'enabled'    => true,          // set to true once the settings below are filled in
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',          // 'tls' or 'ssl'
    'username'   => 'domslibrary7@gmail.com',
    'password'   => 'qhmv cwej ldhq lunk',
    'from_email' => 'domslibrary7@gmail.com',
    'from_name'  => 'Library System',
];
