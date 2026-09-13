# Library System (PHP port)

A PHP + MySQL port of the original Java Swing/NetBeans library system, built to run in **Laragon** and edited in **VSCode**.

## What's new in this update

- **UI redesign**: the old Swing background images are gone, replaced with a clean, modern dark-theme layout (cards, a proper top nav, a responsive book grid instead of a stacked list).
- **More seed data**: the database now comes pre-loaded with 8 sample accounts (admins, teachers, students) and 12 books across a mix of genres, so there's actually something to search and borrow right away.

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

## Setup in Laragon

1. Install [Laragon](https://laragon.org/) (bundles Apache/Nginx, PHP, and MySQL).
2. Copy this whole folder into `laragon/www/php-library` (or rename it to whatever you like — that name becomes part of the URL).
3. Start Laragon ("Start All").
4. Import the database:
   - Open Laragon → **Database** (opens HeidiSQL), or `http://localhost/phpmyadmin`.
   - Go to the **SQL** tab and paste in the contents of `sql/library_db.sql`, then click **Go**.
   - This drops and recreates `library_db` with fresh tables, 8 accounts, and 12 books.
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
