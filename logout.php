<?php
/**
 * Logout: ends the session and returns to the login page.
 * Who: any signed-in user (harmless for others).
 * Input: none.
 * Tables: audit log only (via helper).
 */


// ===== Requires =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';


// ===== Record the logout =====
// Remember who logged out (may be null if session already gone)
$auditUser = $_SESSION['username'] ?? null;
auditLog($pdo, 'logout', $auditUser);


// ===== Destroy the session =====
// Clear all session data
$_SESSION = [];
// Expire the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], '', (bool)$params['secure'], (bool)$params['httponly']);
}
// Destroy the server-side session
session_destroy();


// ===== Redirect =====
// Back to the login page
header('Location: login.php');
exit;
