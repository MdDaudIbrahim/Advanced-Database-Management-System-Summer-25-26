<?php
session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coach') { header('Location: ' . BASE_URL . 'views/auth/login.php'); exit(); }
require_once __DIR__ . '/../../config/db.php';
$conn    = getOracleConnection();
$coachId = $_SESSION['user_id'];
$coach   = oracleQuery($conn, "SELECT * FROM COACH WHERE COACHID=:cid", ['cid' => $coachId])[0] ?? [];
$team    = $_SESSION['team_id'] ? (oracleQuery($conn, "SELECT * FROM TEAM WHERE TEAMID=:tid", ['tid' => $_SESSION['team_id']])[0] ?? []) : [];
$currentPage = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Coach Portal — STMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/coach_sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar">
            <span class="topbar-title">Coach Portal — STMS</span>
            <div class="topbar-right"><div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'CO', 0, 2)) ?></div></div>
        </div>
        <div class="page-header">
            <div class="breadcrumb">Coach / <span>My Profile</span></div>
            <h1 style="font-size:1.25rem;">My Profile</h1>
        </div>
        <div style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;">
            <!-- Avatar Card -->
            <div class="card" style="text-align:center;padding:32px 20px;">
                <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;margin:0 auto 16px;">
                    <?= strtoupper(substr($coach['C_NAME'] ?? 'CO', 0, 2)) ?>
                </div>
                <div style="font-size:1.2rem;font-weight:700;"><?= htmlspecialchars($coach['C_NAME'] ?? '—') ?></div>
                <div class="text-sm text-muted"><?= htmlspecialchars($coach['SPECIALIZATION'] ?? 'Coach') ?></div>
                <span class="badge badge-active" style="margin-top:8px;">Active Coach</span>
                <?php if ($team): ?>
                <div class="divider"></div>
                <div class="text-sm text-muted">Team</div>
                <div class="font-bold"><?= htmlspecialchars($team['TEAMNAME']) ?></div>
                <?php endif; ?>
            </div>
            <!-- Details Card -->
            <div class="card">
                <div class="card-header"><span class="card-title">Profile Information</span></div>
                <div class="card-body">
                    <div style="display:grid;gap:16px;">
                        <?php
                        $fields = [
                            'Coach ID'       => $coach['COACHID']       ?? '—',
                            'Full Name'      => $coach['C_NAME']        ?? '—',
                            'Specialization' => $coach['SPECIALIZATION'] ?? '—',
                            'Email'          => $coach['EMAIL']          ?? '—',
                            'Phone'          => $coach['PHONE_NO']       ?? '—',
                            'Experience'     => isset($coach['EXPERIENCE']) ? $coach['EXPERIENCE'] . ' years' : '—',
                        ];
                        foreach ($fields as $label => $value): ?>
                        <div>
                            <label style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);"><?= $label ?></label>
                            <div class="display-value"><?= htmlspecialchars((string)$value) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>js/main.js"></script>
</body>
</html>
