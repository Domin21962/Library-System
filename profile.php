<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
header('Location: student_info.php');
exit;
