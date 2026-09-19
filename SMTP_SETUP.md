# Doms Library SMTP setup

Teacher and student accounts require email confirmation. Admin accounts do not.

## 1. Create the local configuration

Copy:

`config/local.php.example`

to:

`config/local.php`

Do not upload `config/local.php` or share its contents.

## 2. Gmail settings

Use a Google App Password, not your normal Google account password.

```php
'mail_enabled' => true,
'mail_host' => 'smtp.gmail.com',
'mail_port' => 587,
'mail_encryption' => 'tls',
'mail_username' => 'your-account@gmail.com',
'mail_password' => 'YOUR_16_CHARACTER_APP_PASSWORD',
'mail_from_email' => 'your-account@gmail.com',
'mail_from_name' => 'Doms Library',
```

The sending address should match the authenticated Gmail account unless your provider allows a configured alias.

## 3. Confirm the account email

The teacher or student record must contain a valid email address in the `users.email` column.

Run:

```sql
SELECT username, role, email
FROM users
WHERE role IN ('Teacher', 'Student');
```

## 4. Troubleshooting

- `SMTP is disabled`: set `mail_enabled` to `true`.
- `SMTP is enabled but incomplete`: check every mail setting in `config/local.php`.
- `We could not send the confirmation email`: check the PHP error log, SMTP host and port, App Password, firewall, and provider restrictions.
- Do not disable TLS certificate verification to bypass connection errors.

## 5. Test

After saving `config/local.php`:

1. Restart Laragon.
2. Open `http://localhost/php-library/`.
3. Sign in with a teacher or student account.
4. Confirm the email address receives the “Is this you?” code.
5. Enter the code at `login_verify.php`.
