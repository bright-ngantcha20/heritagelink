<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    // Ensure CSRF token exists for every
    // authenticated page that has forms
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/dashboard.php');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    return [
        'id'         => $_SESSION['user_id']    ?? null,
        'name'       => $_SESSION['user_name']  ?? null,
        'role'       => $_SESSION['role']       ?? null,
        'quarter_id' => $_SESSION['quarter_id'] ?? null,
        'photo'      => $_SESSION['photo']      ?? null,
    ];
}