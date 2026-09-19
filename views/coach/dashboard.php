<?php
// views/coach/dashboard.php
// Figma: "Dashboard - Coach Portal - STMS.png"

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coach') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn    = getOracleConnection();
$coachId = $_SESSION['user_id'];
$teamId  = $_SESSION['team_id'];

// ── Coach Info ─────────────────────────────────────
$coach = oracleQuery($conn, "SELECT * FROM COACH WHERE COACHID=:cid", ['cid' => $coachId])[0] ?? [];
$team  = $teamId ? (oracleQuery($conn, "SELECT * FROM TEAM WHERE TEAMID=:tid", ['tid' => $teamId])[0] ?? []) : [];

// ── KPIs ───────────────────────────────────────────
$playerCount = $teamId ? (oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER WHERE TEAMID=:tid", ['tid' => $teamId])[0]['CNT'] ?? 0) : 0;
$matchCount  = $teamId ? (oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM MATCHES WHERE HOMETEAMID=:tid OR AWAYTEAMID=:tid2", ['tid' => $teamId, 'tid2' => $teamId])[0]['CNT'] ?? 0) : 0;
$winsCount   = $teamId ? (oracleQuery($conn,
    "SELECT COUNT(*) AS CNT FROM MATCHES WHERE UPPER(MATCHSTATUS) IN ('COMPLETED', 'FINISHED') AND
     ((HOMETEAMID=:tid AND HOMESCORE>AWAYSCORE) OR (AWAYTEAMID=:tid2 AND AWAYSCORE>HOMESCORE))",
    ['tid' => $teamId, 'tid2' => $teamId]
)[0]['CNT'] ?? 0) : 0;

// ── Upcoming Matches ───────────────────────────────
$upcomingMatches = [];
if ($teamId) {
    $upcomingMatches = oracleQuery($conn, "
        SELECT M.MATCHID, M.HOMETEAMID, M.AWAYTEAMID, M.MATCHDATE, M.MATCHTIME, M.MATCHSTATUS,
               HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM,
               V.V_NAME AS VENUE, T.T_NAME AS TOURNAMENT
        FROM MATCHES M
        JOIN TEAM HT ON M.HOMETEAMID=HT.TEAMID
        JOIN TEAM AT ON M.AWAYTEAMID=AT.TEAMID
        JOIN VENUE V  ON M.VENUEID=V.VENUEID
        JOIN TOURNAMENT T ON M.TOURNAMENTID=T.TOURNAMENTID
        WHERE (M.HOMETEAMID=:tid OR M.AWAYTEAMID=:tid2)
        AND UPPER(M.MATCHSTATUS) IN ('SCHEDULED', 'CONFIRMED', 'ACTIVE', 'POSTPONED')
        ORDER BY M.MATCHDATE ASC, M.MATCHTIME ASC
        FETCH FIRST 5 ROWS ONLY
    ", ['tid' => $teamId, 'tid2' => $teamId]);
}

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Coach Portal — STMS</title>
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
                <a href="#" class="icon-btn"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></a>
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'CO', 0, 2)) ?></div>
            </div>
        </div>

        <div class="page-header">
            <div class="page-header-row">
                <div>
                    <h1 style="font-size:1.25rem;">Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Coach') ?>!</h1>
                    <p class="page-subtitle">
                        <?= $team ? 'Coaching: <strong>' . htmlspecialchars($team['TEAMNAME']) . '</strong>' : 'No team assigned yet.' ?>
                    </p>
                </div>
                <a href="<?= BASE_URL ?>views/coach/tournament_registration.php" class="btn btn-primary">
                    Register for Tournament
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Players in Team</span>
                    <div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                </div>
                <div class="kpi-value"><?= $playerCount ?></div>
                <a href="<?= BASE_URL ?>views/coach/my_players.php" class="kpi-sub positive">View Players →</a>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Total Matches</span>
                    <div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                </div>
                <div class="kpi-value"><?= $matchCount ?></div>
                <a href="<?= BASE_URL ?>views/coach/match_schedule.php" class="kpi-sub">View Schedule →</a>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Wins</span>
                    <div class="kpi-icon" style="color:#28a745;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></div>
                </div>
                <div class="kpi-value text-green"><?= $winsCount ?></div>
                <span class="kpi-sub positive">Match victories</span>
            </div>
        </div>

        <!-- Upcoming Matches -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Upcoming Matches</span>
                <a href="<?= BASE_URL ?>views/coach/match_schedule.php" class="btn btn-ghost btn-sm text-green">View All →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Opponent</th><th>Tournament</th><th>Venue</th><th>Date</th><th>Time</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($upcomingMatches)): ?>
                        <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No upcoming matches scheduled.</td></tr>
                    <?php else: ?>
                    <?php foreach ($upcomingMatches as $m):
                        $isHome    = (intval($m['HOMETEAMID'] ?? 0) === intval($teamId));
                        $opponent  = $isHome ? $m['AWAY_TEAM'] : $m['HOME_TEAM'];
                        $homeAway  = $isHome ? 'HOME' : 'AWAY';
                        $sRaw      = trim($m['MATCHSTATUS'] ?? 'Scheduled');
                        $s         = strtolower($sRaw);
                        $badgeClass = match($s) {
                            'confirmed'             => 'badge-confirmed',
                            'active', 'in progress' => 'badge-active',
                            'postponed'             => 'badge-pending',
                            'cancelled', 'canceled' => 'badge-closed',
                            default                 => 'badge-scheduled'
                        };
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($opponent) ?></strong>
                            <span class="badge <?= $isHome ? 'badge-active' : 'badge-scheduled' ?>" style="margin-left:6px;font-size:0.6rem;"><?= $homeAway ?></span>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($m['TOURNAMENT']) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($m['VENUE']) ?></td>
                        <td class="text-sm"><?= date('M d, Y', strtotime($m['MATCHDATE'])) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($m['MATCHTIME']) ?></td>
                        <td><span class="badge <?= $badgeClass ?>" style="font-weight:700;"><?= htmlspecialchars(strtoupper($sRaw)) ?></span></td>
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
