<?php
/**
 * send_reminders.php
 *
 * Scans for borrowed books that are due soon (within 2 days) or already overdue,
 * and emails a reminder to each borrower. Meant to be run once a day.
 *
 * Manual run:
 *     php tools/send_reminders.php
 *
 * Automatic daily run (Windows Task Scheduler):
 *   1. Open Task Scheduler -> Create Basic Task
 *   2. Trigger: Daily, pick a time (e.g. 8:00 AM)
 *   3. Action: Start a program
 *      Program:  C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe
 *      (adjust the version folder to match what's on your machine)
 *      Arguments: tools/send_reminders.php
 *      Start in:  C:\laragon\www\php-library
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$mailConfig = require __DIR__ . '/../config/mail.php';
if (empty($mailConfig['enabled'])) {
    echo "Mail is not enabled in config/mail.php - set 'enabled' => true and fill in your\n";
    echo "SMTP credentials first. No reminders were sent.\n";
    exit(0);
}

// Books due within 2 days, or already overdue, that haven't been returned yet
$sql = "SELECT br.id, br.username, br.email, br.due_date, b.title
        FROM borrow_return br
        JOIN book b ON br.book_id = b.book_id
        WHERE br.return_date IS NULL
          AND br.due_date IS NOT NULL
          AND br.due_date <= DATE_ADD(NOW(), INTERVAL 2 DAY)";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No due or overdue books right now. Nothing to send.\n";
    exit(0);
}

echo "Found " . count($rows) . " book(s) due soon or overdue.\n\n";

$sent = 0;
foreach ($rows as $row) {
    if (empty($row['email'])) {
        echo "  - {$row['title']} ({$row['username']}): no email on file, skipped\n";
        continue;
    }

    $dueTimestamp = strtotime($row['due_date']);
    $isOverdue = $dueTimestamp < time();
    $dueDateFormatted = date('F j, Y', $dueTimestamp);

    if ($isOverdue) {
        $subject = "Overdue: \"{$row['title']}\"";
        $body = "Hi {$row['username']},\n\n"
              . "\"{$row['title']}\" was due back on $dueDateFormatted and is now overdue.\n"
              . "Please return it as soon as you can.\n\n"
              . "- Doms Library";
    } else {
        $subject = "Reminder: \"{$row['title']}\" is due soon";
        $body = "Hi {$row['username']},\n\n"
              . "Just a reminder that \"{$row['title']}\" is due back on $dueDateFormatted.\n\n"
              . "- Doms Library";
    }

    $ok = sendLibraryEmail($row['email'], $subject, $body);
    echo "  - {$row['title']} -> {$row['email']}: " . ($ok ? "sent" : "FAILED") . "\n";
    if ($ok) $sent++;
}

echo "\nDone. $sent reminder(s) sent.\n";
