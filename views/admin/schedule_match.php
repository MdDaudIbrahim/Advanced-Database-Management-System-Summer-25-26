<?php
// views/admin/schedule_match.php
// Figma: "Schedule Match - Sports Tournament Management System.png"

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn = getOracleConnection();

$successMsg = ''; $errorMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $successMsg = 'Match deleted successfully from schedule.';
}

// ── Handle Delete Match (POST or GET) ──────────────
$delId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_delete_match'])) {
    $delId = intval($_POST['match_id'] ?? 0);
} elseif (isset($_GET['delete_match'])) {
    $delId = intval($_GET['delete_match']);
}

if ($delId > 0) {
    // 1. Delete tickets tied to this match
    oracleExecute($conn, "DELETE FROM TICKET WHERE MATCHID = :mid", ['mid' => $delId]);
    // 2. Delete match record
    $delOk = oracleExecute($conn, "DELETE FROM MATCHES WHERE MATCHID = :mid", ['mid' => $delId]);
    if ($delOk) {
        $successMsg = "Match #{$delId} deleted successfully from schedule.";
    } else {
        $errorMsg = "Could not delete match #{$delId}.";
    }
}

// ── Handle Update Match ────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_match'])) {
    $editId    = intval($_POST['match_id'] ?? 0);
    $tournId   = intval($_POST['tournament_id'] ?? 0);
    $homeTeam  = intval($_POST['home_team_id'] ?? 0);
    $awayTeam  = intval($_POST['away_team_id'] ?? 0);
    $venueId   = intval($_POST['venue_id'] ?? 0);
    $matchDate = trim($_POST['match_date'] ?? '');
    $matchTime = trim($_POST['match_time'] ?? '');
    $matchType = trim($_POST['match_type'] ?? 'Group Stage');
    $mStatus   = trim($_POST['match_status'] ?? 'Scheduled');
    $homeScore = (isset($_POST['home_score']) && $_POST['home_score'] !== '') ? intval($_POST['home_score']) : null;
    $awayScore = (isset($_POST['away_score']) && $_POST['away_score'] !== '') ? intval($_POST['away_score']) : null;
    $result    = trim($_POST['result'] ?? '');

    $scoreText = null;
    if ($homeScore !== null && $awayScore !== null) {
        $scoreText = "{$homeScore} – {$awayScore}";
    }

    if ($editId > 0 && $tournId && $homeTeam && $awayTeam && $venueId && $matchDate && $matchTime) {
        if ($homeTeam === $awayTeam) {
            $errorMsg = 'Home team and Away team cannot be the same.';
        } else {
            // Check venue conflict excluding current match
            $conflict = oracleQuery($conn,
                "SELECT COUNT(*) AS CNT FROM MATCHES
                 WHERE VENUEID = :vid AND MATCHDATE = :md AND MATCHTIME = :mt AND MATCHID != :mid",
                ['vid' => $venueId, 'md' => $matchDate, 'mt' => $matchTime, 'mid' => $editId]
            );
            if (($conflict[0]['CNT'] ?? 0) > 0) {
                $errorMsg = 'Venue is already booked for another match at this time slot.';
            } else {
                $updateSql = "UPDATE MATCHES 
                              SET TournamentID = :tournid,
                                  HomeTeamID = :homeid,
                                  AwayTeamID = :awayid,
                                  VenueID = :vid,
                                  MatchDate = :md,
                                  MatchTime = :mt,
                                  MatchStatus = :mstatus,
                                  HomeScore = :hscore,
                                  AwayScore = :ascore,
                                  Score = :scoretext,
                                  Result = :result,
                                  MatchType = :mtype,
                                  Match_Type = :mtype2
                              WHERE MatchID = :mid";
                $ok = oracleExecute($conn, $updateSql, [
                    'tournid'   => $tournId,
                    'homeid'    => $homeTeam,
                    'awayid'    => $awayTeam,
                    'vid'       => $venueId,
                    'md'        => $matchDate,
                    'mt'        => $matchTime,
                    'mstatus'   => $mStatus,
                    'hscore'    => $homeScore,
                    'ascore'    => $awayScore,
                    'scoretext' => $scoreText,
                    'result'    => $result,
                    'mtype'     => $matchType,
                    'mtype2'    => $matchType,
                    'mid'       => $editId
                ]);
                if ($ok) {
                    $successMsg = "Match #{$editId} updated successfully!";
                } else {
                    $errorMsg = "Failed to update match #{$editId}. Please check the inputs.";
                }
            }
        }
    } else {
        $errorMsg = "Please fill in all required fields to update the match.";
    }
}

// ── Handle Schedule Match (Create) ─────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !isset($_POST['action_update_match']) && !isset($_POST['action_delete_match'])) {
    $tournId   = intval($_POST['tournament_id'] ?? 0);
    $homeTeam  = intval($_POST['home_team_id'] ?? 0);
    $awayTeam  = intval($_POST['away_team_id'] ?? 0);
    $venueId   = intval($_POST['venue_id'] ?? 0);
    $matchDate = trim($_POST['match_date'] ?? '');
    $matchTime = trim($_POST['match_time'] ?? '');
    $matchType = trim($_POST['match_type'] ?? 'Group Stage');
    $notes     = trim($_POST['notes'] ?? '');

    // Handle Custom Home Team name if typed
    $customHome = trim($_POST['home_team_custom'] ?? '');
    if (!empty($customHome)) {
        $exist = oracleQuery($conn, "SELECT TEAMID FROM TEAM WHERE UPPER(TEAMNAME) = UPPER(:tn)", ['tn' => $customHome]);
        if (!empty($exist)) {
            $homeTeam = intval($exist[0]['TEAMID']);
        } else {
            oracleExecute($conn, "INSERT INTO TEAM (TeamID, TeamName, Category, Sport_Type, HomeCity, CoachID, TournamentID) VALUES (seq_team.NEXTVAL, :tn, 'General', 'General', 'Dhaka', 101, :tournid)", [
                'tn'      => $customHome,
                'tournid' => $tournId ?: null
            ]);
            $newT = oracleQuery($conn, "SELECT MAX(TEAMID) AS TID FROM TEAM WHERE UPPER(TEAMNAME) = UPPER(:tn)", ['tn' => $customHome]);
            $homeTeam = intval($newT[0]['TID'] ?? 0);
        }
    }

    // Handle Custom Away Team name if typed
    $customAway = trim($_POST['away_team_custom'] ?? '');
    if (!empty($customAway)) {
        $exist = oracleQuery($conn, "SELECT TEAMID FROM TEAM WHERE UPPER(TEAMNAME) = UPPER(:tn)", ['tn' => $customAway]);
        if (!empty($exist)) {
            $awayTeam = intval($exist[0]['TEAMID']);
        } else {
            oracleExecute($conn, "INSERT INTO TEAM (TeamID, TeamName, Category, Sport_Type, HomeCity, CoachID, TournamentID) VALUES (seq_team.NEXTVAL, :tn, 'General', 'General', 'Dhaka', 102, :tournid)", [
                'tn'      => $customAway,
                'tournid' => $tournId ?: null
            ]);
            $newT = oracleQuery($conn, "SELECT MAX(TEAMID) AS TID FROM TEAM WHERE UPPER(TEAMNAME) = UPPER(:tn)", ['tn' => $customAway]);
            $awayTeam = intval($newT[0]['TID'] ?? 0);
        }
    }

    if ($homeTeam === $awayTeam && $homeTeam !== 0) {
        $errorMsg = 'Home team and Away team cannot be the same.';
    } elseif ($tournId && $homeTeam && $awayTeam && $venueId && $matchDate && $matchTime) {
        // Check venue conflict
        $conflict = oracleQuery($conn,
            "SELECT COUNT(*) AS CNT FROM MATCHES
             WHERE VENUEID = :vid AND MATCHDATE = :md AND MATCHTIME = :mt",
            ['vid' => $venueId, 'md' => $matchDate, 'mt' => $matchTime]
        );
        if (($conflict[0]['CNT'] ?? 0) > 0) {
            $errorMsg = 'Venue is already booked at this time. Please choose another slot.';
        } else {
            $sql  = "INSERT INTO MATCHES (MatchID, TournamentID, HomeTeamID, AwayTeamID, VenueID, MatchDate, MatchTime, MatchStatus, MatchType, Match_Type)
                     VALUES (seq_matches.NEXTVAL, :tournid, :homeid, :awayid, :vid, :md, :mt, 'Scheduled', :mtype, :mtype2)";
            $ok = oracleExecute($conn, $sql, [
                'tournid' => $tournId,
                'homeid'  => $homeTeam,
                'awayid'  => $awayTeam,
                'vid'     => $venueId,
                'md'      => $matchDate,
                'mt'      => $matchTime,
                'mtype'   => $matchType,
                'mtype2'  => $matchType
            ]);
            if ($ok) { $successMsg = 'Match scheduled successfully!'; }
            else { $errorMsg = 'Could not schedule match. Please check inputs.'; }
        }
    } else {
        $errorMsg = 'Please fill all required fields (or type custom teams).';
    }
}

// ── Dropdown Data ──────────────────────────────────
$tournaments = oracleQuery($conn, "SELECT TOURNAMENTID, T_NAME FROM TOURNAMENT ORDER BY STARTDATE DESC");
$teams       = oracleQuery($conn, "SELECT TEAMID, TEAMNAME FROM TEAM ORDER BY TEAMNAME");
$venues      = oracleQuery($conn, "SELECT VENUEID, V_NAME, CAPACITY FROM VENUE ORDER BY V_NAME");

// ── Recent Matches ─────────────────────────────────
$matches = oracleQuery($conn, "
    SELECT M.MATCHID, M.TOURNAMENTID, M.HOMETEAMID, M.AWAYTEAMID, M.VENUEID,
           M.MATCHDATE, M.MATCHTIME, M.MATCHSTATUS,
           COALESCE(M.MATCH_TYPE, M.MATCHTYPE, 'Group Stage') AS MATCH_TYPE,
           M.HOMESCORE, M.AWAYSCORE, M.SCORE, M.RESULT,
           T.T_NAME AS TOURNAMENT, V.V_NAME AS VENUE,
           HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM
    FROM MATCHES M
    JOIN TOURNAMENT T  ON M.TOURNAMENTID = T.TOURNAMENTID
    JOIN VENUE V       ON M.VENUEID = V.VENUEID
    JOIN TEAM HT       ON M.HOMETEAMID = HT.TEAMID
    JOIN TEAM AT       ON M.AWAYTEAMID = AT.TEAMID
    ORDER BY M.MATCHDATE DESC
");

// Check if specific match is requested for editing via URL
$autoEditMatch = null;
if (isset($_GET['edit'])) {
    $editParamId = intval($_GET['edit']);
    foreach ($matches as $m) {
        if (intval($m['MATCHID']) === $editParamId) {
            $autoEditMatch = $m;
            break;
        }
    }
}

$currentPage = 'matches';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Match — Sports Tournament Management System</title>
    <meta name="description" content="Configure fixture details for upcoming matches, with real-time venue conflict detection.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
    <style>
        .venue-conflict-msg {
            display: none;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #dc3545;
            margin-top: 6px;
        }
        .venue-conflict-msg.show { display: flex; }
        .draft-badge {
            background: #e2e3e5;
            color: #383d41;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 4px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .fixture-section {
            background: var(--bg-table-header);
            padding: 12px 20px;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        .btn-action {
            width: 32px; height: 32px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-action-edit {
            color: #166534;
            border-color: #bbf7d0;
        }
        .btn-action-edit:hover {
            background: #f0fdf4;
            border-color: #166534;
        }
        .btn-action-delete {
            color: #dc2626;
            border-color: #fecaca;
        }
        .btn-action-delete:hover {
            background: #fef2f2;
            border-color: #dc2626;
        }
    </style>
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>
    <main class="main-content">

        <!-- Topbar -->
        <div class="topbar">
            <div class="search-box">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search tournaments, teams...">
            </div>
            <div class="topbar-right">
                <a href="<?= BASE_URL ?>controllers/AuthController.php?action=logout" class="icon-btn" title="Logout"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
            </div>
        </div>

        <!-- Breadcrumb + Page Header -->
        <div class="page-header">
            <div class="breadcrumb" style="margin-bottom:8px;">
                <a href="<?= BASE_URL ?>views/admin/dashboard.php">Matches</a>
                <span style="margin:0 6px;color:var(--text-muted);">›</span>
                <span>Schedule New Match</span>
            </div>
            <div class="page-header-row">
                <div>
                    <h1 style="font-size:1.4rem;">Schedule New Match</h1>
                    <p class="page-subtitle">Configure details for a new fixture in the upcoming season.</p>
                </div>
                <span class="draft-badge">Draft Mode</span>
            </div>
        </div>

        <?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg):   ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

        <!-- Schedule Form Card -->
        <div class="card" style="margin-bottom:24px;">
            <div class="fixture-section">Fixture Details</div>
            <div class="card-body">
                <form method="POST" id="scheduleForm">
                    <!-- Tournament -->
                    <div class="form-group">
                        <label>Tournament Name</label>
                        <select name="tournament_id" class="form-control" required>
                            <option value="">Select Tournament</option>
                            <?php foreach ($tournaments as $t): ?>
                            <option value="<?= $t['TOURNAMENTID'] ?>"><?= htmlspecialchars($t['T_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Home / Away Teams -->
                    <div class="form-row" style="margin-bottom:0;">
                        <!-- Home Team Selection / Custom -->
                        <div>
                            <div class="form-group" id="homeTeamSelectGroup">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <label style="margin-bottom:0;">Home Team *</label>
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="toggleCustomTeam('home', true)" style="font-size:0.75rem;padding:2px 8px;color:var(--primary);font-weight:600;">
                                        ✍️ Type Custom Team
                                    </button>
                                </div>
                                <select name="home_team_id" id="homeTeam" class="form-control" required>
                                    <option value="">Select Home Team</option>
                                    <?php foreach ($teams as $t): ?>
                                    <option value="<?= $t['TEAMID'] ?>"><?= htmlspecialchars($t['TEAMNAME']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" id="homeTeamCustomGroup" style="display:none;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <label style="margin-bottom:0;">Custom Home Team Name *</label>
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="toggleCustomTeam('home', false)" style="font-size:0.75rem;padding:2px 8px;color:var(--text-muted);font-weight:600;">
                                        ← Choose From List
                                    </button>
                                </div>
                                <input type="text" name="home_team_custom" id="homeTeamCustom" class="form-control" placeholder="e.g. Barisal Bulls, AIUB Titans...">
                            </div>
                        </div>

                        <!-- Away Team Selection / Custom -->
                        <div>
                            <div class="form-group" id="awayTeamSelectGroup">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <label style="margin-bottom:0;">Away Team *</label>
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="toggleCustomTeam('away', true)" style="font-size:0.75rem;padding:2px 8px;color:var(--primary);font-weight:600;">
                                        ✍️ Type Custom Team
                                    </button>
                                </div>
                                <select name="away_team_id" id="awayTeam" class="form-control" required>
                                    <option value="">Select Away Team</option>
                                    <?php foreach ($teams as $t): ?>
                                    <option value="<?= $t['TEAMID'] ?>"><?= htmlspecialchars($t['TEAMNAME']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" id="awayTeamCustomGroup" style="display:none;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <label style="margin-bottom:0;">Custom Away Team Name *</label>
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="toggleCustomTeam('away', false)" style="font-size:0.75rem;padding:2px 8px;color:var(--text-muted);font-weight:600;">
                                        ← Choose From List
                                    </button>
                                </div>
                                <input type="text" name="away_team_custom" id="awayTeamCustom" class="form-control" placeholder="e.g. DIU Warriors, Comilla Kings...">
                            </div>
                        </div>
                    </div>

                    <!-- Venue & Stage Selection -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Venue Selection *</label>
                            <select name="venue_id" id="venueSelect" class="form-control" required>
                                <option value="">Select Venue</option>
                                <?php foreach ($venues as $v): ?>
                                <option value="<?= $v['VENUEID'] ?>" data-capacity="<?= $v['CAPACITY'] ?>">
                                    <?= htmlspecialchars($v['V_NAME']) ?> (Cap: <?= number_format($v['CAPACITY']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="venue-conflict-msg" id="conflictMsg">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                Venue is already booked at this time
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Match Stage / Round</label>
                            <select name="match_type" class="form-control">
                                <option value="Group Stage">Group Stage</option>
                                <option value="Quarter-Final">Quarter-Final</option>
                                <option value="Semi-Final">Semi-Final</option>
                                <option value="Final">Final</option>
                                <option value="Friendly / Exhibition">Friendly / Exhibition</option>
                            </select>
                        </div>
                    </div>

                    <!-- Date & Time -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Match Date *</label>
                            <input type="date" name="match_date" id="matchDate" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Start Time (BST) *</label>
                            <input type="time" name="match_time" id="matchTime" class="form-control" required>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <label>Internal Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="4" placeholder="e.g. Broadcast requirements, special security measures..."></textarea>
                    </div>

                    <div style="border-top:1px solid var(--border);padding-top:20px;display:flex;justify-content:flex-end;gap:12px;">
                        <a href="<?= BASE_URL ?>views/admin/dashboard.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="saveBtn">Save Match</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Matches Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Scheduled Matches</span>
                <span class="text-muted text-sm">Latest <?= count($matches) ?></span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Match</th>
                            <th>Tournament</th>
                            <th>Venue</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($matches)): ?>
                        <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">No matches yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($matches as $m):
                        $s = strtolower($m['MATCHSTATUS'] ?? 'scheduled');
                        $badge = match($s) {
                            'scheduled'             => 'badge-scheduled',
                            'confirmed'             => 'badge-confirmed',
                            'completed', 'finished' => 'badge-completed',
                            'active', 'in progress' => 'badge-active',
                            default                 => 'badge-scheduled',
                        };
                        if (!empty($m['SCORE'])) {
                            $scoreDisplay = htmlspecialchars($m['SCORE']);
                        } elseif ($m['HOMESCORE'] !== null && $m['AWAYSCORE'] !== null && $m['HOMESCORE'] !== '') {
                            $scoreDisplay = htmlspecialchars($m['HOMESCORE'] . ' – ' . $m['AWAYSCORE']);
                        } else {
                            $scoreDisplay = '—';
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($m['HOME_TEAM']) ?></strong>
                            <span class="text-muted"> vs </span>
                            <strong><?= htmlspecialchars($m['AWAY_TEAM']) ?></strong>
                            <div class="text-xs text-muted"><?= htmlspecialchars($m['MATCH_TYPE'] ?? '') ?></div>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($m['TOURNAMENT']) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($m['VENUE']) ?></td>
                        <td class="text-sm"><?= date('M d, Y', strtotime($m['MATCHDATE'])) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($m['MATCHTIME']) ?></td>
                        <td class="font-bold"><?= $scoreDisplay ?></td>
                        <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($m['MATCHSTATUS']) ?></span></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <button type="button" class="btn-action btn-action-edit" title="Edit Match Fixture"
                                        onclick='openEditMatchModal(<?= json_encode([
                                            "id"        => (int)$m["MATCHID"],
                                            "tournId"   => (int)$m["TOURNAMENTID"],
                                            "homeId"    => (int)$m["HOMETEAMID"],
                                            "awayId"    => (int)$m["AWAYTEAMID"],
                                            "venueId"   => (int)$m["VENUEID"],
                                            "date"      => $m["MATCHDATE"],
                                            "time"      => $m["MATCHTIME"],
                                            "type"      => $m["MATCH_TYPE"],
                                            "status"    => $m["MATCHSTATUS"],
                                            "homeScore" => $m["HOMESCORE"],
                                            "awayScore" => $m["AWAYSCORE"],
                                            "score"     => $m["SCORE"] ?? "",
                                            "result"    => $m["RESULT"] ?? "",
                                            "homeName"  => $m["HOME_TEAM"],
                                            "awayName"  => $m["AWAY_TEAM"]
                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button type="button" class="btn-action btn-action-delete" title="Delete Match"
                                        onclick="confirmDeleteMatch(<?= (int)$m['MATCHID'] ?>, <?= htmlspecialchars(json_encode($m['HOME_TEAM'] . ' vs ' . $m['AWAY_TEAM']), ENT_QUOTES, 'UTF-8') ?>)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </button>
                            </div>
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

<!-- Edit Match Modal -->
<div id="editMatchModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:var(--radius-xl);padding:28px 32px;width:640px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:14px;">
            <div>
                <h2 style="font-size:1.15rem;font-weight:700;margin:0;">Edit Match Fixture</h2>
                <p style="font-size:0.82rem;color:var(--text-muted);margin:3px 0 0;" id="editMatchSubtitle">Update teams, venue, date, status, or score</p>
            </div>
            <button type="button" id="closeEditMatchModal" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.5rem;line-height:1;">&times;</button>
        </div>
        <form method="POST" id="editMatchForm">
            <input type="hidden" name="action_update_match" value="1">
            <input type="hidden" name="match_id" id="edit_match_id" value="">

            <div class="form-group">
                <label>Tournament *</label>
                <select name="tournament_id" id="edit_tournament_id" class="form-control" required>
                    <option value="">Select Tournament</option>
                    <?php foreach ($tournaments as $t): ?>
                    <option value="<?= $t['TOURNAMENTID'] ?>"><?= htmlspecialchars($t['T_NAME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Home Team *</label>
                    <select name="home_team_id" id="edit_home_team_id" class="form-control" required>
                        <option value="">Select Home Team</option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= $t['TEAMID'] ?>"><?= htmlspecialchars($t['TEAMNAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Away Team *</label>
                    <select name="away_team_id" id="edit_away_team_id" class="form-control" required>
                        <option value="">Select Away Team</option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= $t['TEAMID'] ?>"><?= htmlspecialchars($t['TEAMNAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Venue *</label>
                <select name="venue_id" id="edit_venue_id" class="form-control" required>
                    <option value="">Select Venue</option>
                    <?php foreach ($venues as $v): ?>
                    <option value="<?= $v['VENUEID'] ?>">
                        <?= htmlspecialchars($v['V_NAME']) ?> (Cap: <?= number_format($v['CAPACITY']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="venue-conflict-msg" id="editConflictMsg">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Venue is already booked for another match at this time slot.
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Match Date *</label>
                    <input type="date" name="match_date" id="edit_match_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Start Time (BST) *</label>
                    <input type="text" name="match_time" id="edit_match_time" class="form-control" placeholder="e.g. 03:30 PM" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Match Stage / Round</label>
                    <select name="match_type" id="edit_match_type" class="form-control">
                        <option value="Group Stage">Group Stage</option>
                        <option value="Quarter-Final">Quarter-Final</option>
                        <option value="Semi-Final">Semi-Final</option>
                        <option value="Final">Final</option>
                        <option value="Friendly / Exhibition">Friendly / Exhibition</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Match Status</label>
                    <select name="match_status" id="edit_match_status" class="form-control">
                        <option value="Scheduled">Scheduled</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Active">Active / In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Postponed">Postponed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <!-- Score / Result Section -->
            <div style="background:#f8fafc;border:1px solid var(--border);border-radius:var(--radius-md);padding:14px;margin-bottom:16px;">
                <div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;">
                    Score & Result (Optional)
                </div>
                <div class="form-row" style="margin-bottom:10px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label id="editHomeScoreLabel">Home Score</label>
                        <input type="number" name="home_score" id="edit_home_score" class="form-control" placeholder="e.g. 3 or 180" min="0">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label id="editAwayScoreLabel">Away Score</label>
                        <input type="number" name="away_score" id="edit_away_score" class="form-control" placeholder="e.g. 2 or 176" min="0">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Result Summary / Notes</label>
                    <input type="text" name="result" id="edit_result" class="form-control" placeholder="e.g. Dhaka Gladiators won by 5 wickets, or 3 – 2">
                </div>
            </div>

            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" id="cancelEditMatchModal" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary" id="updateMatchBtn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Match Confirmation Modal -->
<div id="deleteMatchModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:var(--radius-xl);padding:32px;width:460px;max-width:95vw;box-shadow:var(--shadow-lg);">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
            <div style="width:44px;height:44px;border-radius:50%;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <h2 style="font-size:1.15rem;font-weight:700;color:var(--text-main);margin:0;">Delete Match Fixture</h2>
                <p style="font-size:0.85rem;color:var(--text-muted);margin:3px 0 0;">This fixture will be removed from schedule.</p>
            </div>
        </div>
        <p style="font-size:0.92rem;color:var(--text-main);line-height:1.5;margin-bottom:16px;">
            Are you sure you want to delete <strong id="deleteMatchTitle" style="color:#b91c1c;"></strong>?
            <span style="display:block;margin-top:8px;font-size:0.83rem;color:#b45309;background:#fef3c7;padding:8px 12px;border-radius:6px;line-height:1.4;">
                ⚠️ Note: Any booked tickets and payments tied to this match will also be permanently deleted.
            </span>
        </p>
        <form method="POST">
            <input type="hidden" name="action_delete_match" value="1">
            <input type="hidden" name="match_id" id="delete_match_id" value="">
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" id="cancelDeleteMatchModal" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn" style="background:#dc2626;color:#fff;border:none;">Delete Fixture</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>js/main.js"></script>
<script>
// Real-time venue conflict check via fetch (Create Form)
const venueEl = document.getElementById('venueSelect');
const dateEl  = document.getElementById('matchDate');
const timeEl  = document.getElementById('matchTime');
const msgEl   = document.getElementById('conflictMsg');
const saveBtn = document.getElementById('saveBtn');

async function checkVenueConflict() {
    const vid = venueEl.value, date = dateEl.value, time = timeEl.value;
    if (!vid || !date || !time) { msgEl.classList.remove('show'); return; }
    try {
        const resp = await fetch(`<?= BASE_URL ?>api/venue_check.php?venue_id=${vid}&date=${date}&time=${time}`);
        const data = await resp.json();
        if (data.conflict) {
            msgEl.classList.add('show');
            venueEl.classList.add('error');
        } else {
            msgEl.classList.remove('show');
            venueEl.classList.remove('error');
        }
    } catch(e) { /* network error — allow submit */ }
}

[venueEl, dateEl, timeEl].forEach(el => el.addEventListener('change', checkVenueConflict));

// Prevent same team on both sides (Create Form)
document.getElementById('homeTeam').addEventListener('change', function() {
    const awayOpts = document.getElementById('awayTeam').options;
    for (let o of awayOpts) o.disabled = (o.value === this.value && o.value !== '');
});
document.getElementById('awayTeam').addEventListener('change', function() {
    const homeOpts = document.getElementById('homeTeam').options;
    for (let o of homeOpts) o.disabled = (o.value === this.value && o.value !== '');
});

function toggleCustomTeam(side, isCustom) {
    const selectGroup = document.getElementById(side + 'TeamSelectGroup');
    const customGroup = document.getElementById(side + 'TeamCustomGroup');
    const selectEl    = document.getElementById(side + 'Team');
    const customEl    = document.getElementById(side + 'TeamCustom');

    if (isCustom) {
        selectGroup.style.display = 'none';
        customGroup.style.display = 'block';
        selectEl.removeAttribute('required');
        selectEl.value = '';
        customEl.setAttribute('required', 'required');
        customEl.focus();
    } else {
        customGroup.style.display = 'none';
        selectGroup.style.display = 'block';
        customEl.removeAttribute('required');
        customEl.value = '';
        selectEl.setAttribute('required', 'required');
    }
}

// ── Edit Match Modal Handling ──────────────────────
const editModal       = document.getElementById('editMatchModal');
const closeEditBtn    = document.getElementById('closeEditMatchModal');
const cancelEditBtn   = document.getElementById('cancelEditMatchModal');
if (closeEditBtn)  closeEditBtn.addEventListener('click', () => editModal.style.display = 'none');
if (cancelEditBtn) cancelEditBtn.addEventListener('click', () => editModal.style.display = 'none');
editModal.addEventListener('click', e => { if (e.target === editModal) editModal.style.display = 'none'; });

function openEditMatchModal(data) {
    document.getElementById('edit_match_id').value      = data.id || '';
    document.getElementById('edit_tournament_id').value = data.tournId || '';
    document.getElementById('edit_home_team_id').value  = data.homeId || '';
    document.getElementById('edit_away_team_id').value  = data.awayId || '';
    document.getElementById('edit_venue_id').value      = data.venueId || '';
    document.getElementById('edit_match_date').value    = data.date || '';
    document.getElementById('edit_match_time').value    = data.time || '';
    document.getElementById('edit_match_type').value    = data.type || 'Group Stage';
    document.getElementById('edit_match_status').value  = data.status || 'Scheduled';
    document.getElementById('edit_home_score').value    = (data.homeScore !== null && data.homeScore !== undefined) ? data.homeScore : '';
    document.getElementById('edit_away_score').value    = (data.awayScore !== null && data.awayScore !== undefined) ? data.awayScore : '';
    document.getElementById('edit_result').value        = data.result || '';

    const subtitle = document.getElementById('editMatchSubtitle');
    if (subtitle && data.homeName && data.awayName) {
        subtitle.textContent = `${data.homeName} vs ${data.awayName} (#${data.id})`;
    }
    const homeLabel = document.getElementById('editHomeScoreLabel');
    if (homeLabel && data.homeName) homeLabel.textContent = `${data.homeName} Score`;
    const awayLabel = document.getElementById('editAwayScoreLabel');
    if (awayLabel && data.awayName) awayLabel.textContent = `${data.awayName} Score`;

    document.getElementById('editConflictMsg').classList.remove('show');
    editModal.style.display = 'flex';
}

// ── Delete Match Modal Handling ────────────────────
const deleteModal     = document.getElementById('deleteMatchModal');
const cancelDeleteBtn = document.getElementById('cancelDeleteMatchModal');
if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', () => deleteModal.style.display = 'none');
deleteModal.addEventListener('click', e => { if (e.target === deleteModal) deleteModal.style.display = 'none'; });

function confirmDeleteMatch(id, title) {
    document.getElementById('delete_match_id').value = id;
    document.getElementById('deleteMatchTitle').textContent = title;
    deleteModal.style.display = 'flex';
}

// Close on Escape key
window.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (editModal)   editModal.style.display = 'none';
        if (deleteModal) deleteModal.style.display = 'none';
    }
});

// Edit modal: venue conflict check with self exclusion
const editVenueEl = document.getElementById('edit_venue_id');
const editDateEl  = document.getElementById('edit_match_date');
const editTimeEl  = document.getElementById('edit_match_time');
const editMsgEl   = document.getElementById('editConflictMsg');
const editMatchId = document.getElementById('edit_match_id');

async function checkEditVenueConflict() {
    const vid = editVenueEl.value, date = editDateEl.value, time = editTimeEl.value, mid = editMatchId.value;
    if (!vid || !date || !time) { editMsgEl.classList.remove('show'); return; }
    try {
        const resp = await fetch(`<?= BASE_URL ?>api/venue_check.php?venue_id=${vid}&date=${date}&time=${time}&match_id=${mid}`);
        const data = await resp.json();
        if (data.conflict) {
            editMsgEl.classList.add('show');
            editVenueEl.classList.add('error');
        } else {
            editMsgEl.classList.remove('show');
            editVenueEl.classList.remove('error');
        }
    } catch(e) {}
}
[editVenueEl, editDateEl, editTimeEl].forEach(el => el.addEventListener('change', checkEditVenueConflict));

// Edit modal: Prevent same home and away team
document.getElementById('edit_home_team_id').addEventListener('change', function() {
    const awayOpts = document.getElementById('edit_away_team_id').options;
    for (let o of awayOpts) o.disabled = (o.value === this.value && o.value !== '');
});
document.getElementById('edit_away_team_id').addEventListener('change', function() {
    const homeOpts = document.getElementById('edit_home_team_id').options;
    for (let o of homeOpts) o.disabled = (o.value === this.value && o.value !== '');
});

// Auto-open edit modal if ?edit=XXX was in URL
<?php if ($autoEditMatch): ?>
document.addEventListener('DOMContentLoaded', () => {
    openEditMatchModal(<?= json_encode([
        "id"        => (int)$autoEditMatch["MATCHID"],
        "tournId"   => (int)$autoEditMatch["TOURNAMENTID"],
        "homeId"    => (int)$autoEditMatch["HOMETEAMID"],
        "awayId"    => (int)$autoEditMatch["AWAYTEAMID"],
        "venueId"   => (int)$autoEditMatch["VENUEID"],
        "date"      => $autoEditMatch["MATCHDATE"],
        "time"      => $autoEditMatch["MATCHTIME"],
        "type"      => $autoEditMatch["MATCH_TYPE"],
        "status"    => $autoEditMatch["MATCHSTATUS"],
        "homeScore" => $autoEditMatch["HOMESCORE"],
        "awayScore" => $autoEditMatch["AWAYSCORE"],
        "score"     => $autoEditMatch["SCORE"] ?? "",
        "result"    => $autoEditMatch["RESULT"] ?? "",
        "homeName"  => $autoEditMatch["HOME_TEAM"],
        "awayName"  => $autoEditMatch["AWAY_TEAM"]
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
});
<?php endif; ?>
</script>
</body>
</html>
