<?php
session_start();
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['username'])) {
    redirect('main.php');
} else {
    redirect('login.php');
}
