<?php
session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coach') { 
    header('Location: ' . BASE_URL . 'views/auth/login.php'); 
    exit(); 
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';

$conn    = getOracleConnection();
$coachId = $_SESSION['user_id'];
$teamId  = $_SESSION['team_id'];
$successMsg = ''; 
$errorMsg = '';

// Handle Registration
if (is_post()) {
    $tournId = intval($_POST['tournament_id'] ?? 0);
    $regFee  = floatval($_POST['reg_fee'] ?? 5000);

    if ($tournId && $teamId) {
        // 1. Check if already registered
        $check = oracleQuery($conn, "
            SELECT COUNT(*) AS CNT 
            FROM REGISTRATION 
            WHERE TEAMID = :tid AND TOURNAMENTID = :tournid
        ", ['tid' => $teamId, 'tournid' => $tournId]);

        if (($check[0]['CNT'] ?? 0) > 0) {
            $errorMsg = '⚠️ Your team is already registered for this tournament!';
        } else {
            // 2. Insert into REGISTRATION table
            $regInserted = oracleExecute($conn, "
                INSERT INTO REGISTRATION (RegistrationID, RegDate, RegFee, TeamID, TournamentID) 
                VALUES (seq_registration.NEXTVAL, CURRENT_TIMESTAMP, :fee, :tid, :tournid)
            ", [
                'fee'     => $regFee,
                'tid'     => $teamId,
                'tournid' => $tournId
            ]);

            // 3. Update TEAM tournament
            oracleExecute($conn, "
                UPDATE TEAM 
                SET TOURNAMENTID = :tournid 
                WHERE TEAMID = :tid
            ", [
                'tournid' => $tournId,
                'tid'     => $teamId
            ]);

            // 4. Automatically generate scheduled match fixture for this tournament!
            ensureTournamentFixture($conn, $teamId, $tournId);

            // Fetch tournament info for success message
            $tournInfo = oracleQuery($conn, "SELECT T_NAME, STARTDATE, ENDDATE FROM TOURNAMENT WHERE TOURNAMENTID = :tournid", ['tournid' => $tournId]);
            $tournName = esc($tournInfo[0]['T_NAME'] ?? 'Tournament');
            $successMsg = "🎉 <strong>Registration Successful!</strong> Your team is officially enrolled in <em>{$tournName}</em> and your match fixture has been scheduled! <a href='" . BASE_URL . "views/coach/match_schedule.php?tourn_id={$tournId}' class='btn btn-sm btn-outline' style='margin-left:10px;font-weight:700;'>View Match Schedule →</a>";
        }
    } else {
        $errorMsg = 'Please select a tournament.';
    }
}

// Data
$tournaments     = oracleQuery($conn, "SELECT TOURNAMENTID, T_NAME, STARTDATE, ENDDATE, SPORT_TYPE, LOCATION FROM TOURNAMENT ORDER BY STARTDATE DESC");
$myRegistrations = $teamId ? oracleQuery($conn, "
    SELECT R.REGISTRATIONID, R.TOURNAMENTID, R.REGDATE, R.REGFEE, T.T_NAME, T.SPORT_TYPE, T.STARTDATE, T.ENDDATE, T.LOCATION
    FROM REGISTRATION R
    JOIN TOURNAMENT T ON R.TOURNAMENTID = T.TOURNAMENTID
    WHERE R.TEAMID = :tid
    ORDER BY R.REGDATE DESC
", ['tid' => $teamId]) : [];

$currentPage = 'tournament';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Registration — Coach Portal — STMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/forms.css">
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
            <div class="breadcrumb">Coach / <span>Tournament Registration</span></div>
            <h1 style="font-size:1.25rem;">Tournament Registration</h1>
            <p class="page-subtitle">Register your team in upcoming tournaments and track your fixtures.</p>
        </div>

        <?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg):   ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

        <?php if (!$teamId): ?>
        <div class="alert alert-warning">No team assigned to your account. Contact admin first.</div>
        <?php else: ?>

        <div style="display:grid;grid-template-columns:360px 1fr;gap:24px;align-items:start;">
            <!-- Registration Form -->
            <div class="card">
                <div class="card-header"><span class="card-title">Register Team</span></div>
                <div class="card-body">
                    <div class="registration-notice">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Official tournament registration creates scheduled match fixtures for your team.
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Tournament *</label>
                            <select name="tournament_id" class="form-control" required>
                                <option value="">Choose a tournament</option>
                                <?php foreach ($tournaments as $t): ?>
                                <option value="<?= $t['TOURNAMENTID'] ?>"><?= esc($t['T_NAME']) ?> — <?= esc($t['SPORT_TYPE'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Registration Fee (BDT)</label>
                            <input type="number" name="reg_fee" class="form-control" value="5000" readonly style="background:var(--bg-table-header);">
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">Confirm Registration & Schedule Match</button>
                    </form>
                </div>
            </div>

            <!-- Available Tournaments -->
            <div class="card">
                <div class="card-header"><span class="card-title">Available Tournaments</span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Name</th><th>Sport</th><th>Start</th><th>End</th><th>Location</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($tournaments as $t):
                            $now = time(); $s = strtotime($t['STARTDATE']); $e = strtotime($t['ENDDATE']);
                            if ($now < $s) { $status='Open'; $b='badge-open'; }
                            elseif ($now<=$e) { $status='Active'; $b='badge-active'; }
                            else { $status='Closed'; $b='badge-closed'; }
                        ?>
                        <tr>
                            <td><strong><?= esc($t['T_NAME']) ?></strong></td>
                            <td><?= esc($t['SPORT_TYPE'] ?? '—') ?></td>
                            <td class="text-sm"><?= date('M d, Y', $s) ?></td>
                            <td class="text-sm"><?= date('M d, Y', $e) ?></td>
                            <td class="text-sm"><?= esc($t['LOCATION'] ?? '—') ?></td>
                            <td><span class="badge <?= $b ?>"><?= $status ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- My Enrolled Tournaments -->
        <div class="card" style="margin-top:24px;">
            <div class="card-header">
                <span class="card-title">My Team's Enrolled Tournaments</span>
                <span class="text-muted text-sm"><?= count($myRegistrations) ?> tournaments registered</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Tournament</th><th>Sport</th><th>Enrolled Date</th><th>Fee Paid</th><th>Tournament Dates</th><th>Venue</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($myRegistrations)): ?>
                        <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">No tournament registrations found for your team. Use the form above to enroll!</td></tr>
                    <?php else: ?>
                    <?php foreach ($myRegistrations as $mr): ?>
                        <tr>
                            <td><strong><?= esc($mr['T_NAME']) ?></strong></td>
                            <td><?= esc($mr['SPORT_TYPE'] ?? 'Football') ?></td>
                            <td class="text-sm"><?= date('M d, Y', strtotime($mr['REGDATE'])) ?></td>
                            <td class="text-sm font-semibold">৳<?= number_format($mr['REGFEE']) ?></td>
                            <td class="text-sm"><?= date('M d', strtotime($mr['STARTDATE'])) ?> – <?= date('M d, Y', strtotime($mr['ENDDATE'])) ?></td>
                            <td class="text-sm"><?= esc($mr['LOCATION'] ?? 'Main Field') ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>views/coach/match_schedule.php?tourn_id=<?= $mr['TOURNAMENTID'] ?>" class="btn btn-ghost btn-sm text-green" style="font-weight:700;">View Matches →</a>
                            </td>
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
