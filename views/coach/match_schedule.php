<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!defined('BASE_URL')) define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coach') { 
    header('Location: ' . BASE_URL . 'views/auth/login.php'); 
    exit(); 
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';

$conn   = getOracleConnection();
$teamId = $_SESSION['team_id'];
$filterTournId = intval($_GET['tourn_id'] ?? 0);

// Get coach's team name
$teamInfo = $teamId ? oracleQuery($conn, "SELECT TEAMNAME, SPORT_TYPE FROM TEAM WHERE TEAMID=:tid", ['tid' => $teamId])[0] ?? [] : [];
$teamName = $teamInfo['TEAMNAME'] ?? 'My Team';

// Fetch all enrolled tournaments for the filter dropdown
$enrolledTourns = $teamId ? oracleQuery($conn, "
    SELECT DISTINCT T.TOURNAMENTID, T.T_NAME, T.SPORT_TYPE
    FROM REGISTRATION R
    JOIN TOURNAMENT T ON R.TOURNAMENTID = T.TOURNAMENTID
    WHERE R.TEAMID = :tid
    ORDER BY T.STARTDATE DESC
", ['tid' => $teamId]) : [];

// If viewing a specific tournament, ensure fixture exists
$filterTourn = null;
if ($filterTournId > 0) {
    ensureTournamentFixture($conn, $teamId, $filterTournId);
    $filterTourn = oracleQuery($conn, "SELECT * FROM TOURNAMENT WHERE TOURNAMENTID = :id", ['id' => $filterTournId])[0] ?? null;
} else {
    // For all enrolled tournaments, make sure fixture is generated
    foreach ($enrolledTourns as $et) {
        ensureTournamentFixture($conn, $teamId, $et['TOURNAMENTID']);
    }
}

// Fetch matches
if ($filterTournId > 0) {
    $matches = $teamId ? oracleQuery($conn, "
        SELECT M.MATCHID, M.HOMETEAMID, M.AWAYTEAMID, M.MATCHDATE, M.MATCHTIME, M.MATCHSTATUS,
               COALESCE(M.MATCH_TYPE, M.MATCHTYPE, 'Group Stage') AS MATCH_TYPE,
               HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM,
               V.V_NAME AS VENUE, T.T_NAME AS TOURNAMENT,
               M.HOMESCORE, M.AWAYSCORE, M.SCORE, M.RESULT
        FROM MATCHES M
        JOIN TEAM HT ON M.HOMETEAMID=HT.TEAMID
        JOIN TEAM AT ON M.AWAYTEAMID=AT.TEAMID
        JOIN VENUE V  ON M.VENUEID=V.VENUEID
        JOIN TOURNAMENT T ON M.TOURNAMENTID=T.TOURNAMENTID
        WHERE (M.HOMETEAMID=:tid OR M.AWAYTEAMID=:tid2)
        AND M.TOURNAMENTID = :tournid
        ORDER BY M.MATCHDATE DESC, M.MATCHTIME DESC
    ", ['tid' => $teamId, 'tid2' => $teamId, 'tournid' => $filterTournId]) : [];
} else {
    $matches = $teamId ? oracleQuery($conn, "
        SELECT M.MATCHID, M.HOMETEAMID, M.AWAYTEAMID, M.MATCHDATE, M.MATCHTIME, M.MATCHSTATUS,
               COALESCE(M.MATCH_TYPE, M.MATCHTYPE, 'Group Stage') AS MATCH_TYPE,
               HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM,
               V.V_NAME AS VENUE, T.T_NAME AS TOURNAMENT,
               M.HOMESCORE, M.AWAYSCORE, M.SCORE, M.RESULT
        FROM MATCHES M
        JOIN TEAM HT ON M.HOMETEAMID=HT.TEAMID
        JOIN TEAM AT ON M.AWAYTEAMID=AT.TEAMID
        JOIN VENUE V  ON M.VENUEID=V.VENUEID
        JOIN TOURNAMENT T ON M.TOURNAMENTID=T.TOURNAMENTID
        WHERE M.HOMETEAMID=:tid OR M.AWAYTEAMID=:tid2
        ORDER BY M.MATCHDATE DESC, M.MATCHTIME DESC
    ", ['tid' => $teamId, 'tid2' => $teamId]) : [];
}

$currentPage = 'schedule';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Match Schedule — Coach Portal — STMS</title>
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
            <div class="breadcrumb">Coach / <span>Match Schedule</span></div>
            <h1 style="font-size:1.25rem;">Match Schedule</h1>
            <p class="page-subtitle">All scheduled matches and tournament fixtures for <strong><?= esc($teamName) ?></strong>.</p>
        </div>

        <?php if ($filterTourn): ?>
        <!-- Tournament Filter Banner -->
        <div class="card" style="margin-bottom:20px;border-left:4px solid var(--primary);padding:16px 20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);font-weight:700;">Tournament Fixtures Detail</div>
                    <div style="font-size:1.2rem;font-weight:800;color:var(--text-primary);margin-top:2px;"><?= esc($filterTourn['T_NAME']) ?></div>
                    <div style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">
                        Sport: <strong class="text-primary"><?= esc($filterTourn['SPORT_TYPE'] ?? 'General') ?></strong> &bull; 
                        Venue: <strong><?= esc($filterTourn['LOCATION'] ?? 'Main Stadium') ?></strong> &bull; 
                        Dates: <strong><?= date('M d', strtotime($filterTourn['STARTDATE'])) ?> – <?= date('M d, Y', strtotime($filterTourn['ENDDATE'])) ?></strong>
                    </div>
                </div>
                <div class="flex gap-8">
                    <a href="<?= BASE_URL ?>views/coach/tournament_registration.php" class="btn btn-ghost btn-sm">← Back to Registration</a>
                    <a href="<?= BASE_URL ?>views/coach/match_schedule.php" class="btn btn-outline btn-sm">View All Matches</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header" style="flex-wrap:wrap;gap:12px;">
                <div>
                    <span class="card-title">Team Fixtures</span>
                    <span class="text-muted text-sm" style="margin-left:8px;"><?= count($matches) ?> matches</span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <label style="font-size:0.82rem;color:var(--text-muted);margin:0;">Filter Tournament:</label>
                    <select class="form-control" style="width:auto;padding:4px 10px;font-size:0.82rem;" onchange="if(this.value){ location.href='<?= BASE_URL ?>views/coach/match_schedule.php?tourn_id='+this.value; } else { location.href='<?= BASE_URL ?>views/coach/match_schedule.php'; }">
                        <option value="">All Enrolled Tournaments</option>
                        <?php foreach ($enrolledTourns as $et): ?>
                        <option value="<?= $et['TOURNAMENTID'] ?>" <?= ($filterTournId == $et['TOURNAMENTID']) ? 'selected' : '' ?>>
                            <?= esc($et['T_NAME']) ?> (<?= esc($et['SPORT_TYPE'] ?? '') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Opponent & Role</th>
                            <th>Type</th>
                            <th>Tournament</th>
                            <th>Venue</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($matches)): ?>
                        <tr><td colspan="9" class="text-center text-muted" style="padding:32px;">No matches scheduled for this tournament yet. Use the Tournament Registration page to enroll.</td></tr>
                    <?php else: ?>
                    <?php foreach ($matches as $m):
                        $isHome    = (intval($m['HOMETEAMID'] ?? 0) === intval($teamId));
                        $opponent  = $isHome ? $m['AWAY_TEAM'] : $m['HOME_TEAM'];
                        $myScore   = $isHome ? $m['HOMESCORE'] : $m['AWAYSCORE'];
                        $oppScore  = $isHome ? $m['AWAYSCORE'] : $m['HOMESCORE'];
                        
                        // Score display: Coach team score first
                        $hasNumericScores = ($myScore !== null && $oppScore !== null && $myScore !== '' && $oppScore !== '');
                        if ($hasNumericScores) {
                            $score = "{$myScore} — {$oppScore}";
                        } elseif (!empty(trim($m['SCORE'] ?? ''))) {
                            $score = htmlspecialchars(trim($m['SCORE']));
                        } else {
                            $score = '<span class="text-muted" style="font-weight:600;">TBD</span>';
                        }
                        
                        // Status badge styling
                        $statusRaw   = trim($m['MATCHSTATUS'] ?? 'Scheduled');
                        $statusLower = strtolower($statusRaw);
                        $statusBadge = match($statusLower) {
                            'completed', 'finished'         => 'badge-completed',
                            'active', 'in progress', 'live' => 'badge-active',
                            'scheduled'                     => 'badge-scheduled',
                            'confirmed'                     => 'badge-confirmed',
                            'postponed'                     => 'badge-pending',
                            'cancelled', 'canceled'         => 'badge-closed',
                            default                         => 'badge-pending'
                        };

                        // Result calculation
                        $result      = '—'; 
                        $resultBadge = '';
                        $isFinished  = in_array($statusLower, ['completed', 'finished']);
                        $isActive    = in_array($statusLower, ['active', 'in progress', 'live']);

                        if ($isFinished) {
                            if ($hasNumericScores) {
                                $mScoreInt = intval($myScore);
                                $oScoreInt = intval($oppScore);
                                if ($mScoreInt > $oScoreInt)      { $result = 'WIN';  $resultBadge = 'badge-active'; }
                                elseif ($mScoreInt < $oScoreInt)  { $result = 'LOSS'; $resultBadge = 'badge-closed'; }
                                else                              { $result = 'DRAW'; $resultBadge = 'badge-pending'; }
                            } elseif (!empty(trim($m['RESULT'] ?? ''))) {
                                $resText = strtolower(trim($m['RESULT']));
                                $myLower = strtolower(trim($teamName));
                                $oppLower = strtolower(trim($opponent));
                                if (str_contains($resText, $myLower) && (str_contains($resText, 'won') || str_contains($resText, 'win') || str_contains($resText, 'victory'))) {
                                    $result = 'WIN'; $resultBadge = 'badge-active';
                                } elseif (str_contains($resText, $oppLower) && (str_contains($resText, 'won') || str_contains($resText, 'win'))) {
                                    $result = 'LOSS'; $resultBadge = 'badge-closed';
                                } elseif (str_contains($resText, 'draw') || str_contains($resText, 'tied') || str_contains($resText, 'tie')) {
                                    $result = 'DRAW'; $resultBadge = 'badge-pending';
                                } else {
                                    $result = 'COMPLETED'; $resultBadge = 'badge-completed';
                                }
                            } else {
                                $result = 'COMPLETED'; $resultBadge = 'badge-completed';
                            }
                        } elseif ($isActive) {
                            if ($hasNumericScores) {
                                $mScoreInt = intval($myScore);
                                $oScoreInt = intval($oppScore);
                                if ($mScoreInt > $oScoreInt)      { $result = 'LEADING';  $resultBadge = 'badge-active'; }
                                elseif ($mScoreInt < $oScoreInt)  { $result = 'TRAILING'; $resultBadge = 'badge-pending'; }
                                else                              { $result = 'TIED';     $resultBadge = 'badge-pending'; }
                            } else {
                                $result = 'LIVE'; $resultBadge = 'badge-active';
                            }
                        } elseif ($statusLower === 'cancelled' || $statusLower === 'canceled') {
                            $result = 'CANCELLED';
                            $resultBadge = 'badge-closed';
                        } elseif ($statusLower === 'postponed') {
                            $result = 'POSTPONED';
                            $resultBadge = 'badge-pending';
                        }

                        $adminResultNote = trim($m['RESULT'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:700;color:var(--text-primary);font-size:0.95rem;">vs <?= htmlspecialchars($opponent) ?></div>
                            <div style="margin-top:3px;">
                                <span class="badge <?= $isHome ? 'badge-active' : 'badge-scheduled' ?>" style="font-size:0.65rem;padding:2px 6px;font-weight:700;">
                                    <?= $isHome ? '🏠 Home Match' : '✈️ Away Match' ?>
                                </span>
                            </div>
                        </td>
                        <td class="text-sm font-semibold"><?= htmlspecialchars($m['MATCH_TYPE'] ?? 'Group Stage') ?></td>
                        <td class="text-sm"><?= htmlspecialchars($m['TOURNAMENT']) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($m['VENUE']) ?></td>
                        <td class="text-sm font-semibold"><?= date('M d, Y', strtotime($m['MATCHDATE'])) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($m['MATCHTIME']) ?></td>
                        <td class="font-bold text-center" style="font-size:0.95rem;"><?= $score ?></td>
                        <td><span class="badge <?= $statusBadge ?>" style="font-weight:700;"><?= htmlspecialchars(strtoupper($statusRaw)) ?></span></td>
                        <td>
                            <?php if ($resultBadge): ?>
                                <span class="badge <?= $resultBadge ?>" style="font-weight:800;letter-spacing:0.04em;"><?= $result ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                            <?php if (!empty($adminResultNote)): ?>
                                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;font-weight:600;line-height:1.25;" title="Admin Match Notes">
                                    <?= htmlspecialchars($adminResultNote) ?>
                                </div>
                            <?php endif; ?>
                        </td>
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
