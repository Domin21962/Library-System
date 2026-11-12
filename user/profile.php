<?php
/**
 * Profile entry point: just forwards to student_info.php.
 * Who: any signed-in user.
 * Input: none.
 * Tables: none.
 */
// Strict typing for this file
declare(strict_types=1);


// ===== Requires & login check =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();


// ===== Redirect =====
// Profile page lives in student_info.php
redirect('user/student_info.php');
exit;
