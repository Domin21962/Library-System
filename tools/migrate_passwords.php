<?php
/*
 * One-off CLI tool: replaces legacy plain-text passwords with password_hash() hashes.
 * Access: command line only (web requests get 403). Inputs: none.
 * Tables: users (reads username/password, updates password).
 */

// Run from Laragon terminal: php tools/migrate_passwords.php


// ===== Access check (CLI only) =====

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }


// ===== Requires =====

require_once __DIR__ . '/../config/db.php';


// ===== Migration: hash legacy plain-text passwords =====

// Read every user's stored password
$stmt = $pdo->query('SELECT username, password FROM users');
// Prepared update: write back the new hash
$update = $pdo->prepare('UPDATE users SET password = ? WHERE username = ?');
$count = 0;
foreach ($stmt as $user) {
    $password = (string)$user['password'];
    // Skip empty or already-hashed values (bcrypt $2y$ / argon2)
    if ($password !== '' && !str_starts_with($password, '$2y$') && !str_starts_with($password, '$argon2')) {
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['username']]);
        $count++;
    }
}


// ===== Report =====

printf("Migrated %d legacy password(s).\n", $count);
