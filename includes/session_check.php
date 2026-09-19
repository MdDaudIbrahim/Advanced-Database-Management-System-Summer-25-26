<?php
// includes/session_check.php
// Role-based session protection middleware
// Usage: include at the top of every protected page

session_start();

function requireLogin($requiredRole = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit();
    }
    if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
        // Wrong role — redirect to their own dashboard
        $role = $_SESSION['role'];
        if ($role === 'admin')    { header('Location: ' . BASE_URL . 'views/admin/dashboard.php'); exit(); }
        if ($role === 'coach')    { header('Location: ' . BASE_URL . 'views/coach/dashboard.php'); exit(); }
        if ($role === 'staff')    { header('Location: ' . BASE_URL . 'views/staff/dashboard.php'); exit(); }
        if ($role === 'customer') { header('Location: ' . BASE_URL . 'views/customer/home.php'); exit(); }
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getRole() {
    return $_SESSION['role'] ?? null;
}

function getUserName() {
    return $_SESSION['name'] ?? 'User';
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getTeamId() {
    return $_SESSION['team_id'] ?? null;
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit();
}
