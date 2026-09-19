<?php
session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') { header('Location: ' . BASE_URL . 'views/auth/login.php'); exit(); }
require_once __DIR__ . '/../../config/db.php';
$conn = getOracleConnection();

$totalPlayers = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER")[0]['CNT'] ?? 0;
$totalCoaches = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM COACH")[0]['CNT'] ?? 0;
$pendingPay   = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TICKET WHERE PAYMENT_STATUS='Pending'")[0]['CNT'] ?? 0;
$totalTickets = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TICKET")[0]['CNT'] ?? 0;

$recentTickets = oracleQuery($conn, "
    SELECT TK.TICKETID, TK.SEATNO, TK.PRICE, TK.PAYMENT_STATUS,
           S.S_NAME, HT.TEAMNAME||' vs '||AT.TEAMNAME AS MATCH_LABEL
    FROM TICKET TK
    JOIN SPECTATOR S ON TK.SPECTATORID=S.SPECTATORID
    JOIN MATCHES M   ON TK.MATCHID=M.MATCHID
    JOIN TEAM HT     ON M.HOMETEAMID=HT.TEAMID
    JOIN TEAM AT     ON M.AWAYTEAMID=AT.TEAMID
    ORDER BY TK.TICKETID DESC
    FETCH FIRST 8 ROWS ONLY
");
$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Staff Portal — STMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/staff_sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar">
            <span class="topbar-title">STMS Staff Portal</span>
            <div class="topbar-right">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" placeholder="Search records...">
                </div>
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'ST', 0, 2)) ?></div>
            </div>
        </div>
        <div class="page-header">
            <h1 style="font-size:1.25rem;">Staff Overview</h1>
            <p class="page-subtitle">Data entry and record management dashboard.</p>
        </div>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-card-top"><span class="kpi-label">Total Players</span><div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div></div>
                <div class="kpi-value"><?= $totalPlayers ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top"><span class="kpi-label">Total Coaches</span><div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div></div>
                <div class="kpi-value"><?= $totalCoaches ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top"><span class="kpi-label">Pending Payments</span><div class="kpi-icon" style="color:#fd7e14;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
                <div class="kpi-value text-orange"><?= $pendingPay ?></div>
                <a href="<?= BASE_URL ?>views/staff/ticket_payments.php" class="kpi-sub negative">Resolve →</a>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top"><span class="kpi-label">Total Tickets</span><div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div></div>
                <div class="kpi-value"><?= $totalTickets ?></div>
            </div>
        </div>
        <!-- Recent Tickets -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Recent Tickets</span>
                <a href="<?= BASE_URL ?>views/staff/ticket_payments.php" class="btn btn-ghost btn-sm text-green">View All →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Spectator</th><th>Match</th><th>Seat</th><th>Price</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTickets as $tk): ?>
                    <tr>
                        <td class="font-bold">#<?= $tk['TICKETID'] ?></td>
                        <td><?= htmlspecialchars($tk['S_NAME']) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($tk['MATCH_LABEL']) ?></td>
                        <td class="text-center">Seat <?= $tk['SEATNO'] ?></td>
                        <td class="font-semibold">৳<?= number_format($tk['PRICE']) ?></td>
                        <td><span class="badge <?= strtolower($tk['PAYMENT_STATUS']) === 'paid' ? 'badge-paid' : 'badge-pending' ?>"><?= $tk['PAYMENT_STATUS'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>js/main.js"></script>
</body>
</html>
