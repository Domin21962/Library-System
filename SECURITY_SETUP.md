# Doms Library Security Setup

## Before deployment
1. Revoke any SMTP app password that was previously present in the project ZIP. Create a new one only after the old one is revoked.
2. Copy `config/local.php.example` to `config/local.php` and set database/mail values. Never publish `config/local.php`.
3. Prefer a dedicated MySQL user (`doms_library`) instead of `root`; review `sql/security_migration.sql` and replace the placeholder password before running it.
4. Run `php tools/migrate_passwords.php` from the Laragon terminal to hash legacy plaintext passwords. Back up `library_db` first.
5. Keep HTTPS enabled for phone access and use the correct certificate/SAN configuration.

## Security protections included
- `password_hash()` / `password_verify()` for registration, login, and password changes.
- One-time migration of legacy plaintext passwords on successful login, plus a CLI migration script.
- HttpOnly/SameSite session cookies, Secure cookies under HTTPS, strict session mode, and session ID regeneration after login.
- CSRF tokens on HTML forms.
- Role checks for admin/teacher pages, with session role consistency validation.
- Login failure tracking and temporary lockout after repeated failures.
- Input validation and session-derived borrower identity (posted username/email are not trusted).
- Audit events for login, logout, QR login, account registration, notifications, and unauthorized access.
- Database and SMTP secrets moved to environment variables or untracked `config/local.php`.

## Important
The application cannot recover a plaintext password after it has been hashed. Run a database backup first. Test login, registration, password change, QR login, borrow, return, admin actions, and email sending on a local copy before using this in production.

## Login email confirmation and roles

Run `sql/login_confirmation_and_roles.sql` once. It adds a persistent `users.role` column and the `login_confirmations` table. Existing AM./TC./SD. accounts are mapped to their legacy roles. New usernames do not need a prefix; new self-registered accounts remain Student accounts. Assign Teacher/Admin roles only through a trusted administrator/database workflow.

Password login now requires a one-time 6-digit code sent to the account email. Configure SMTP in `config/local.php` or environment variables before enabling this feature. If email sending fails, login is denied.
