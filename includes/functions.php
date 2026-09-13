<?php
// Equivalent of the role logic in models/User.java and MainForm.getUserRole()
function getUserRole($username) {
    if (str_starts_with($username, "AM.")) return "Admin";
    if (str_starts_with($username, "TC.")) return "Teacher";
    if (str_starts_with($username, "SD.")) return "Student";
    return "Unknown";
}

// Equivalent of RegisterForm.isValidUsername()
function isValidUsername($username) {
    return preg_match('/^(TC|SD|AM)\.[a-zA-Z]+$/', $username) === 1;
}

// Simple flash-message style redirect (keeps pages() thin, like the JOptionPane dialogs)
function redirect($url) {
    header("Location: $url");
    exit;
}

function requireLogin() {
    if (empty($_SESSION['username'])) {
        redirect('login.php');
    }
}

function requireAdmin() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        redirect('main.php');
    }
}
