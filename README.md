# Doms Library (PHP port)

A PHP + MySQL port of the original Java Swing/NetBeans library system, built to run in **Laragon** and edited in **VSCode**.

## What's new in this update

- **UI redesign**: the old Swing background images are gone, replaced with a clean, modern dark-theme layout (cards, a proper top nav, a responsive book grid instead of a stacked list).
- **More seed data**: the database now comes pre-loaded with 8 sample accounts (admins, teachers, students) and 51 popular, well-known books across a mix of genres, so there's actually something to search and borrow right away.
- **Public-domain only**: every book in the seed data was first published before ~1930, meaning copyright has expired and they're free to use, no licensing concerns. Modern copyrighted titles were deliberately left out. `book_content` holds a short plot synopsis rather than the full novel text — full books run far too long to store in one field, but every title's complete text is freely available at [Project Gutenberg](https://www.gutenberg.org) if you want to link out to it.

## What maps to what

| Original (Java) | Ported to (PHP) |
|---|---|
| `database/DatabaseConnection.java` | `config/db.php` |
| `models/User.java` role logic | `includes/functions.php` → `getUserRole()` |
| `controllers/LoginController.java` + `views/LoginForm.java` | `login.php` |
| `controllers/RegisterController.java` + `views/RegisterForm.java` | `register.php` |
| `views/MainForm.java` + `controllers/BookController.searchBooks()` | `main.php` |
| `views/AddBookForm.java` + `BookController.addBook()` | `add_book.php` |
| `views/RemoveBookForm.java` + `BookController.removeBook()` | `remove_book.php` |
| `views/BorrowReturn.java` | `borrow_return.php` |
| `views/StudentInfoForm.java` | `student_info.php` |
| `models/Session.java` (static fields) | PHP `$_SESSION` |
| Swing `JOptionPane` popups | Inline `.error` / `.success` messages on each page |

Core logic is unchanged from the original: passwords are still stored/compared in plain text, and the email field on the Student Info page is intentionally left blank (the original `Session.setCurrentEmail()` was never called anywhere, so this reproduces that same quirk).

## Reading full books

Clicking any book card opens `book.php`, a reader page showing that book's complete text.

The full texts aren't shipped in the SQL file — a single novel runs hundreds of thousands
of words, and 51 of them would make the schema file enormous. Instead they're downloaded
straight into your database by a one-time importer:

```
php tools/import_texts.php
```

Steps:

1. Import `sql/library_db.sql` (creates the catalog).
2. Import `sql/add_gutenberg_ids.sql` (adds the `full_text` / `gutenberg_id` columns and
   tags each book with its Project Gutenberg ID).
3. Run `php tools/import_texts.php` from the project folder. It downloads each complete
   book, strips the Gutenberg license header/footer, and saves the text to
   `book.full_text`. Takes a few minutes and needs an internet connection.
4. Open any book in the app — the whole text is now there.

Re-running is safe; already-imported books are skipped. Use `--force` to re-download.

Every title in the catalog is public domain (first published before ~1930), so the
complete texts are free to download, store, and read.

## Sending real emails (borrow confirmations + due-date reminders)

The app can send actual emails - a confirmation right when someone borrows a book, and
due-date reminders via a scheduled script. No Composer or external library install is
needed; `includes/mailer.php` is a small self-contained SMTP client.

**1. Turn it on:** open `config/mail.php` and fill in real SMTP credentials, then set
`'enabled' => true`. For Gmail specifically:
- Turn on 2-Step Verification on the Google account (if not already on)
- Generate an **App Password** at https://myaccount.google.com/apppasswords
- Use that 16-character app password as `password` (not the normal Gmail password)
- `host` stays `smtp.gmail.com`, `port` `587`, `encryption` `tls`

Any other SMTP provider (Outlook, a school email system, SendGrid, etc.) works too -
just swap in its host/port/credentials.

**2. Add the due_date column:** run `sql/add_due_date.sql` once (adds a `due_date`
column to `borrow_return`, set automatically to 7 days after borrowing).

**3. Confirmation emails** send automatically the moment someone borrows or returns a
book - styled as an HTML receipt (transaction ID, book, borrower, dates) rather than a
plain text note. No extra setup once mail is enabled.

**4. Due-date reminders** run via a script, not automatically in the background (PHP
web pages can't run on a timer by themselves). Two ways to trigger it:
- Manually: `php tools/send_reminders.php`
- Automatically every day: set it up in Windows Task Scheduler to run
  `php tools/send_reminders.php` once a day (full instructions are in the comment
  at the top of that file).

It emails anyone whose book is due within 2 days or already overdue.


### Login when SMTP is not configured

For local Laragon development, `config/mail.php` defaults to `enabled => false`. Teacher and student login confirmation is still enforced, so those accounts remain blocked until SMTP is enabled and configured. Admin accounts bypass email confirmation by design. Once SMTP is enabled in `config/local.php`, login sends a one-time confirmation code and blocks login if sending fails.

To enable confirmation emails, copy `config/local.php.example` to `config/local.php`, add your SMTP credentials (use a Gmail App Password rather than your normal Gmail password), and set `mail_enabled` to `true`.

## Password change confirmation (Teacher / Student)

Teacher and Student accounts can no longer change their password in one step from
`student_info.php` ("My Info"). Instead:

1. They enter their current password + new password and click **Send Confirmation Code**.
2. A 6-digit code is emailed to the address on file (styled HTML email, same sender as
   everything else in `includes/mailer.php`). The new password isn't saved yet.
3. They enter that code on the same page to confirm - only then does the password
   actually change. Codes expire after 10 minutes, allow 5 wrong attempts before needing
   a resend, and a **Resend Code** / **Cancel** option is available at that step.

Admin accounts are unaffected - they still change password immediately, no code needed.

**Setup:** run `sql/add_password_reset_codes.sql` once (creates the
`password_reset_codes` table). Requires mail to be enabled in `config/mail.php` (see
above); if there's no email on file for an account, it's told to add one first.

## Admin dashboard

Admin accounts land on `admin.php` after login instead of the student catalog -
a distinct amber-themed dashboard with:

- Stats: total books, registered users, currently borrowed, overdue count
- A book management table (every book, delete inline, plus a link to add new ones)
- A user management table (every account, role shown as a badge, delete inline -
  admins can't delete their own currently-logged-in account)
- A recent activity feed of the latest borrow/return transactions across all users

Students and teachers still land on `main.php` (the search/browse catalog) as before.
Admins can jump to either view via the nav links on each page.

## Setup in Laragon

1. Install [Laragon](https://laragon.org/) (bundles Apache/Nginx, PHP, and MySQL).
2. Copy this whole folder into `laragon/www/php-library` (or rename it to whatever you like — that name becomes part of the URL).
3. Start Laragon ("Start All").
4. Import the database:
   - Open Laragon → **Database** (opens HeidiSQL), or `http://localhost/phpmyadmin`.
   - Go to the **SQL** tab and paste in the contents of `sql/library_db.sql`, then click **Go**.
   - This drops and recreates `library_db` with fresh tables, 8 accounts, and 51 books.
5. Visit **http://localhost/php-library/** (adjust to your folder name).

## Sample accounts

| Username | Password | Role |
|---|---|---|
| `AM.Admin` | `admin123` | Admin |
| `AM.Cruz` | `admin123` | Admin |
| `TC.Reyes` | `teacher123` | Teacher |
| `TC.Santos` | `teacher123` | Teacher |
| `SD.Juan` | `student123` | Student |
| `SD.Maria` | `student123` | Student |
| `SD.Pedro` | `student123` | Student |
| `SD.Ana` | `student123` | Student |

Only `Admin` accounts see the "Add Book" / "Remove Book" options.

## Setup in VSCode

1. Open the project folder in VSCode.
2. Recommended extensions: **PHP Intelephense** and **PHP Debug**.
3. Laragon already serves the files from `www/` — just edit and refresh the browser, no separate dev server needed.

## Notes

- Usernames must follow the pattern `AM.Name`, `TC.Name`, or `SD.Name` — the prefix determines the role.
- Admin-only pages (`add_book.php`, `remove_book.php`) check the logged-in role server-side, since URLs are directly reachable on the web (unlike a hidden Swing button).

## QR code login

The system now supports temporary QR-code login tokens. A logged-in user can open `qr_login.php`, generate a QR code, and scan it with another device. The token is server-side, expires after 60 seconds, and is invalidated after one successful login. The QR never contains the user's password.

### Setup

Run `sql/add_qr_login.sql` once in the `library_db` database. The application also attempts to create the table automatically if it does not exist.

### Using it on a phone

For a phone to open the QR URL, the PC and phone must be on the same network and the library site must be reachable from the phone. Open the site on the PC using its LAN IP (for example `http://192.168.1.10/php-library/`) instead of `http://localhost/php-library/`, then generate the QR. Scanning a QR containing `localhost` from a phone will point to the phone itself and will not work.

The login page also has a **Login with QR Code** option that can use the device camera when browser camera access and internet access for the scanner library are available.

## QR Login camera scanning on phones

Live camera scanning from a browser requires a **secure context (HTTPS)** on modern Android/iOS browsers. If the site is opened as `http://192.168.x.x/...`, the browser can block `getUserMedia()` camera access even when the camera works normally.

This version of Doms Library therefore:

- uses the rear/environment camera when available;
- asks for camera permission only after the user taps **Start Camera**;
- supports switching between available cameras;
- validates that scanned QR codes are Doms Library login URLs before navigating;
- keeps the existing 60-second, one-time QR token security;
- provides a native phone camera/photo fallback when live camera access is unavailable;
- clearly tells the user when HTTPS is required.

### Laragon / phone setup

For live camera scanning, serve the project through HTTPS and open the HTTPS address on the phone. The phone and PC must still be able to reach the PC over the same network.

If you are testing with the existing `http://192.168.1.43/...` address, the **Scan a QR image with your phone camera** fallback can be used without enabling browser live-camera access.

Do not disable browser security or Android security settings to force camera access.

## Real-time admin borrow/return notifications

The admin dashboard now receives borrow and return events in real time using Server-Sent Events (SSE). Admins see a live notification badge, in-page toast, and optional desktop/browser notification. The system automatically creates the `admin_notifications` table when the database user has permission to create tables.

For manual database setup, run `sql/add_admin_notifications.sql` in the `library_db` database.

For desktop notifications, click **Enable desktop notifications** in the admin notification bell. Browser system notifications require a secure context such as HTTPS (or localhost).


## Laragon database defaults

The included `config/local.php` uses the common Laragon MySQL setup:

- Host: `127.0.0.1`
- Database: `library_db`
- Username: `root`
- Password: empty

If your MySQL root account uses a password, update `config/local.php`.
