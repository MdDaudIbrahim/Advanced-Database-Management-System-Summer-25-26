<?php
// views/coach/my_team.php + my_players.php + tournament_registration.php + match_schedule.php + profile.php
// All remaining Coach portal views

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coach') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';
$conn    = getOracleConnection();
$coachId = $_SESSION['user_id'];
$teamId  = $_SESSION['team_id'];

$team    = $teamId ? (oracleQuery($conn, "SELECT T.*, C.C_NAME AS COACH_NAME, C.SPECIALIZATION FROM TEAM T JOIN COACH C ON T.COACHID=C.COACHID WHERE T.TEAMID=:tid", ['tid' => $teamId])[0] ?? []) : [];
$players = $teamId ? oracleQuery($conn, "SELECT * FROM PLAYER WHERE TEAMID=:tid ORDER BY JERSEY_NO", ['tid' => $teamId]) : [];

$currentPage = 'my_team';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Team — Coach Portal — STMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/coach_sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <span class="topbar-title">Coach Portal — STMS</span>
            <div class="topbar-right">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'CO', 0, 2)) ?></div>
            </div>
        </div>

        <div class="page-header">
            <div class="breadcrumb">Coach / <span>My Team</span></div>
            <h1 style="font-size:1.25rem;">My Team</h1>
            <p class="page-subtitle">Overview of your team and roster.</p>
        </div>

        <?php if (!$team): ?>
        <div class="alert alert-warning">No team assigned to your account yet. Contact the admin.</div>
        <?php else: ?>

        <!-- Team Info Card -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-body" style="display:grid;grid-template-columns:auto 1fr auto;gap:20px;align-items:center;">
                <div style="width:64px;height:64px;border-radius:var(--radius-lg);background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:var(--primary);">
                    <?= strtoupper(substr($team['TEAMNAME'], 0, 2)) ?>
                </div>
                <div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--text-primary);"><?= htmlspecialchars($team['TEAMNAME']) ?></div>
                    <div class="text-sm text-muted">Sport: <?= htmlspecialchars($team['SPORT_TYPE'] ?? '—') ?></div>
                    <div class="text-sm text-muted">Coach: <?= htmlspecialchars($team['COACH_NAME']) ?> — <?= htmlspecialchars($team['SPECIALIZATION'] ?? '') ?></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:2rem;font-weight:800;color:var(--primary);"><?= count($players) ?></div>
                    <div class="text-sm text-muted">Players</div>
                </div>
            </div>
        </div>

        <!-- Player Roster -->
        <div class="card">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="card-title">Player Roster</span>
                    <span class="badge badge-scheduled"><?= count($players) ?> players</span>
                </div>
                <a href="<?= BASE_URL ?>views/coach/my_players.php" class="btn btn-primary btn-sm">+ Add Player</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>Player</th><th>Position</th><th>Jersey</th><th>Height</th><th>Weight</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($players)): ?>
                        <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No players yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($players as $i => $p): ?>
                    <tr>
                        <td class="text-muted text-sm"><?= $i + 1 ?></td>
                        <td>
                            <div class="flex gap-8" style="align-items:center;">
                                <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <?= renderPlayerAvatar($p['POSITION'], $p['JERSEY_NO'], 30) ?>
                                </div>
                                <strong><?= htmlspecialchars($p['P_NAME']) ?></strong>
                            </div>
                        </td>
                        <td><span class="badge badge-scheduled"><?= htmlspecialchars($p['POSITION']) ?></span></td>
                        <td class="text-center font-bold"><?= $p['JERSEY_NO'] ?></td>
                        <td class="text-sm"><?= $p['HEIGHT'] ? $p['HEIGHT'] . ' cm' : '—' ?></td>
                        <td class="text-sm"><?= $p['WEIGHT'] ? $p['WEIGHT'] . ' kg' : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>
<script src="<?= BASE_URL ?>js/main.js"></script>
</body>
</html>
