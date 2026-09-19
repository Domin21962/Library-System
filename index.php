<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['username'])) {
    redirect(($_SESSION['role'] ?? '') === 'Admin' ? 'admin.php' : 'main.php');
} else {
    redirect('login.php');
}
