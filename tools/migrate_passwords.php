<?php
// Run from Laragon terminal: php tools/migrate_passwords.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query('SELECT username, password FROM users');
$update = $pdo->prepare('UPDATE users SET password = ? WHERE username = ?');
$count = 0;
foreach ($stmt as $user) {
    $password = (string)$user['password'];
    if ($password !== '' && !str_starts_with($password, '$2y$') && !str_starts_with($password, '$argon2')) {
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['username']]);
        $count++;
    }
}
printf("Migrated %d legacy password(s).\n", $count);
