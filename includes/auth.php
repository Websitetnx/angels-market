<?php
/**
 * Authentication Helper
 */
session_start();

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isClient() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client';
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Please login to continue.';
        header('Location: ' . BASE_URL . 'client/login.php');
        exit();
    }
}

function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        $_SESSION['flash_error'] = 'Access denied. Admin only.';
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit();
    }
}

function requireClient() {
    if (!isLoggedIn() || !isClient()) {
        $_SESSION['flash_error'] = 'Please login as a client.';
        header('Location: ' . BASE_URL . 'client/login.php');
        exit();
    }
}

function setFlash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function getFlash($type) {
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}

function getCurrentUser($pdo) {
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}
