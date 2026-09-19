<?php
session_start(); define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') { header('Location: ' . BASE_URL . 'views/auth/login.php'); exit(); }
$currentPage = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile — Staff Portal — STMS</title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/forms.css">
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/staff_sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar"><span class="topbar-title">STMS Staff Portal</span><div class="topbar-right"><div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'ST', 0, 2)) ?></div></div></div>
        <div class="page-header">
            <div class="breadcrumb">Staff / <span>My Profile</span></div>
            <h1 style="font-size:1.25rem;">Staff Profile</h1>
        </div>
        <div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start;">
            <div class="card" style="text-align:center;padding:32px 20px;">
                <div style="width:72px;height:72px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;margin:0 auto 16px;"><?= strtoupper(substr($_SESSION['name'] ?? 'ST', 0, 2)) ?></div>
                <div style="font-size:1.1rem;font-weight:700;"><?= htmlspecialchars($_SESSION['name'] ?? 'Staff Member') ?></div>
                <div class="text-sm text-muted">Data Entry Staff</div>
                <span class="badge badge-active" style="margin-top:8px;">Active</span>
            </div>
            <div class="card">
                <div class="card-header"><span class="card-title">Account Information</span></div>
                <div class="card-body">
                    <div style="display:grid;gap:16px;">
                        <div>
                            <label>Full Name</label>
                            <div class="display-value"><?= htmlspecialchars($_SESSION['name'] ?? 'Rahat Karim') ?></div>
                        </div>
                        <div>
                            <label>Role</label>
                            <div class="display-value">Data Entry Staff <span class="badge-readonly">Staff</span></div>
                        </div>
                        <div>
                            <label>Access Level</label>
                            <div class="display-value">View Players, Coaches & Process Payments</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>js/main.js"></script>
</body>
</html>
