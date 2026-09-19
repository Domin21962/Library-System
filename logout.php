<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
$auditUser = $_SESSION['username'] ?? null;
auditLog($pdo, 'logout', $auditUser);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], '', (bool)$params['secure'], (bool)$params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
