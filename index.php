<?php
// index.php — Entry Point
// Redirects to login page

define('BASE_URL', '/ALL CODES/ADMS STMS/');

session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin')    header('Location: ' . BASE_URL . 'views/admin/dashboard.php');
    elseif ($role === 'coach')    header('Location: ' . BASE_URL . 'views/coach/dashboard.php');
    elseif ($role === 'staff')    header('Location: ' . BASE_URL . 'views/staff/dashboard.php');
    elseif ($role === 'customer') header('Location: ' . BASE_URL . 'views/customer/home.php');
    else header('Location: ' . BASE_URL . 'views/auth/login.php');
} else {
    header('Location: ' . BASE_URL . 'views/auth/login.php');
}
exit();
