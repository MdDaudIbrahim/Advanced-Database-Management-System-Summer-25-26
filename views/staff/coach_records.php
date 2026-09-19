<?php
session_start(); define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') { header('Location: ' . BASE_URL . 'views/auth/login.php'); exit(); }
require_once __DIR__ . '/../../config/db.php';
$conn   = getOracleConnection();
$coaches = oracleQuery($conn, "SELECT C.*, T.TEAMNAME FROM COACH C LEFT JOIN TEAM T ON T.COACHID=C.COACHID ORDER BY C.C_NAME");
$currentPage = 'coaches';
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Coach Records — Staff Portal — STMS</title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/staff_sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar"><span class="topbar-title">STMS Staff Portal</span><div class="topbar-right"><div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'ST', 0, 2)) ?></div></div></div>
        <div class="page-header">
            <div class="breadcrumb">Staff / <span>Coach Records</span></div>
            <h1 style="font-size:1.25rem;">Coach Records</h1>
            <p class="page-subtitle"><?= count($coaches) ?> coaches registered in the system.</p>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Coach Name</th><th>Specialization</th><th>Email</th><th>Phone</th><th>Team</th></tr></thead>
                    <tbody>
                    <?php if (empty($coaches)): ?>
                        <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No coach records.</td></tr>
                    <?php else: ?>
                    <?php foreach ($coaches as $c): ?>
                    <tr>
                        <td class="text-muted text-sm"><?= $c['COACHID'] ?></td>
                        <td>
                            <div class="flex gap-8" style="align-items:center;">
                                <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.72rem;color:var(--primary);"><?= strtoupper(substr($c['C_NAME'],0,2)) ?></div>
                                <strong><?= htmlspecialchars($c['C_NAME']) ?></strong>
                            </div>
                        </td>
                        <td><span class="badge badge-scheduled"><?= htmlspecialchars($c['SPECIALIZATION'] ?? '—') ?></span></td>
                        <td class="text-sm"><?= htmlspecialchars($c['EMAIL'] ?? '—') ?></td>
                        <td class="text-sm"><?= htmlspecialchars($c['PHONE_NO'] ?? '—') ?></td>
                        <td class="text-sm"><?= $c['TEAMNAME'] ? htmlspecialchars($c['TEAMNAME']) : '<span class="text-muted">No team</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>js/main.js"></script>
</body>
</html>
