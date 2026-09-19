<?php
// views/admin/player_management.php
// Sports Tournament Management System (STMS) - Player Directory & Registration

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';
$conn = getOracleConnection();

$filterTeam = intval($_GET['team_id'] ?? 0);
$searchQ    = trim($_GET['q'] ?? '');
$successMsg = ''; $errorMsg = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $successMsg = 'Player record has been deleted and synced with Oracle.';
}

// ── Handle Delete Player ───────────────────────────
if (isset($_GET['delete'])) {
    $delId = intval($_GET['delete']);
    if ($delId > 0) {
        $delOk = oracleExecute($conn, "DELETE FROM PLAYER WHERE PlayerID = :pid", ['pid' => $delId]);
        if ($delOk) {
            header('Location: ' . BASE_URL . 'views/admin/player_management.php?msg=deleted');
            exit();
        } else {
            $errorMsg = 'Could not delete player. It may be referenced in match records.';
        }
    }
}

// ── Handle Add Player ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add'])) {
    $pName    = trim($_POST['p_name'] ?? '');
    $position = trim($_POST['position'] ?? '') ?: 'Athlete';
    $jerseyNo = intval($_POST['jersey_no'] ?? 0);
    $dob      = trim($_POST['dob'] ?? '') ?: null;
    $height   = intval($_POST['height'] ?? 0) ?: null;
    $weight   = intval($_POST['weight'] ?? 0) ?: null;
    $teamId   = intval($_POST['team_id_form'] ?? 0);

    if (empty($pName) || $teamId <= 0) {
        $errorMsg = 'Player name and team selection are required.';
    } elseif ($jerseyNo <= 0 || $jerseyNo > 99) {
        $errorMsg = 'Jersey number must be between 1 and 99.';
    } else {
        // Check duplicate jersey number in this team
        $dup = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER WHERE TEAMID = :tid AND (JERSEYNO = :jno OR JERSEY_NO = :jno2)", [
            'tid'  => $teamId,
            'jno'  => $jerseyNo,
            'jno2' => $jerseyNo
        ]);

        if (($dup[0]['CNT'] ?? 0) > 0) {
            $errorMsg = "Jersey #{$jerseyNo} is already assigned to another player in this team.";
        } else {
            $sql = "INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, Height, Weight, DOB, TeamID)
                    VALUES (seq_player.NEXTVAL, :pname, :pos, :jno, :jno2, :h, :w, :dob, :tid)";
            $params = [
                'pname' => $pName,
                'pos'   => $position,
                'jno'   => $jerseyNo,
                'jno2'  => $jerseyNo,
                'h'     => $height,
                'w'     => $weight,
                'dob'   => $dob,
                'tid'   => $teamId
            ];
            
            $ok = oracleExecute($conn, $sql, $params);
            if ($ok) {
                $successMsg = "Player '<strong>" . htmlspecialchars($pName) . "</strong>' (#{$jerseyNo}) registered and synced to Oracle successfully!";
            } else {
                $errorMsg = "Failed to register player. Please check inputs.";
            }
        }
    }
}

// ── Handle Edit Player ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit'])) {
    $playerId = intval($_POST['player_id'] ?? 0);
    $pName    = trim($_POST['p_name'] ?? '');
    $position = trim($_POST['position'] ?? '') ?: 'Athlete';
    $jerseyNo = intval($_POST['jersey_no'] ?? 0);
    $dob      = trim($_POST['dob'] ?? '') ?: null;
    $height   = intval($_POST['height'] ?? 0) ?: null;
    $weight   = intval($_POST['weight'] ?? 0) ?: null;
    $teamId   = intval($_POST['team_id_form'] ?? 0);

    if ($playerId <= 0 || empty($pName) || $teamId <= 0) {
        $errorMsg = 'Valid player details and team selection are required.';
    } elseif ($jerseyNo <= 0 || $jerseyNo > 99) {
        $errorMsg = 'Jersey number must be between 1 and 99.';
    } else {
        // Check duplicate jersey excluding this player
        $dup = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER WHERE TEAMID = :tid AND (JERSEYNO = :jno OR JERSEY_NO = :jno2) AND PLAYERID != :pid", [
            'tid'  => $teamId,
            'jno'  => $jerseyNo,
            'jno2' => $jerseyNo,
            'pid'  => $playerId
        ]);

        if (($dup[0]['CNT'] ?? 0) > 0) {
            $errorMsg = "Jersey #{$jerseyNo} is already assigned to another player in this team.";
        } else {
            $sql = "UPDATE PLAYER 
                    SET P_Name = :pname, Position = :pos, JerseyNo = :jno, Jersey_No = :jno2, Height = :h, Weight = :w, DOB = :dob, TeamID = :tid 
                    WHERE PlayerID = :pid";
            $params = [
                'pname' => $pName,
                'pos'   => $position,
                'jno'   => $jerseyNo,
                'jno2'  => $jerseyNo,
                'h'     => $height,
                'w'     => $weight,
                'dob'   => $dob,
                'tid'   => $teamId,
                'pid'   => $playerId
            ];
            $ok = oracleExecute($conn, $sql, $params);
            if ($ok) {
                $successMsg = "Player '<strong>" . htmlspecialchars($pName) . "</strong>' updated and synced to Oracle successfully!";
            } else {
                $errorMsg = "Failed to update player.";
            }
        }
    }
}

// ── Data ───────────────────────────────────────────
$teams = oracleQuery($conn, "SELECT TEAMID, TEAMNAME FROM TEAM ORDER BY TEAMNAME");

$whereClause = 'WHERE 1=1';
if ($filterTeam) $whereClause .= " AND P.TEAMID = {$filterTeam}";
if ($searchQ)    $whereClause .= " AND UPPER(P.P_NAME) LIKE UPPER('%{$searchQ}%')";

$players = oracleQuery($conn, "
    SELECT P.PLAYERID, P.P_NAME, P.POSITION, COALESCE(P.JERSEYNO, P.JERSEY_NO) AS JERSEY_NO, P.DOB,
           P.HEIGHT, P.WEIGHT, T.TEAMNAME, T.TEAMID
    FROM PLAYER P
    JOIN TEAM T ON P.TEAMID = T.TEAMID
    {$whereClause}
    ORDER BY T.TEAMNAME, COALESCE(P.JERSEYNO, P.JERSEY_NO)
");

// Quick stats
$totalPlayers = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER")[0]['CNT'] ?? 0;
$totalTeams   = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TEAM")[0]['CNT'] ?? 0;
$avgAge       = oracleQuery($conn, "SELECT ROUND(AVG(EXTRACT(YEAR FROM SYSDATE) - EXTRACT(YEAR FROM DOB)),1) AS AVG_AGE FROM PLAYER WHERE DOB IS NOT NULL")[0]['AVG_AGE'] ?? '—';

$currentPage = 'players';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Directory — Sports Tournament Management System</title>
    <meta name="description" content="Manage and register players for the current tournament season.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
    <style>
        .player-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 1080px) {
            .player-layout { grid-template-columns: 1fr; }
        }
        .quick-stats-card {
            background: var(--bg-table-header);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .quick-stats-header {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }
        .quick-stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
        }
        .quick-stat-row:last-child { border-bottom: none; }
        .quick-stat-val {
            font-weight: 700;
            color: var(--primary);
        }
        .player-name-link {
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
        }
        .player-name-link:hover { text-decoration: underline; }

        .form-label-compact {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .form-row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        /* Action Buttons */
        .btn-action {
            width: 30px; height: 30px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
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

        /* Modal styling */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 16px;
        }
        .modal-box {
            background: #fff;
            border-radius: 12px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            animation: modalFadeIn 0.2s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header {
            padding: 16px 20px;
            background: var(--bg-table-header);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 14px 20px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>
    <main class="main-content">

        <!-- Topbar -->
        <div class="topbar">
            <form method="GET" class="search-box" style="min-width:300px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" placeholder="Search players, IDs or teams..." value="<?= htmlspecialchars($searchQ) ?>">
            </form>
            <div class="topbar-right">
                <span class="topbar-title" style="font-weight:500;font-size:0.95rem;">Sports Tournament Management System</span>
                <a href="<?= BASE_URL ?>controllers/AuthController.php?action=logout" class="icon-btn" title="Logout"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1 style="font-size:1.6rem;font-weight:800;">Player Directory</h1>
            <p class="page-subtitle">Manage and register players for the current tournament season.</p>
        </div>

        <?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg):   ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

        <div class="player-layout">

            <!-- Left: Player Table -->
            <div class="card">
                <div class="table-wrap">
                    <table id="playersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Team</th>
                                <th>Jersey #</th>
                                <th>Date of Birth</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($players)): ?>
                            <tr><td colspan="5" class="text-center text-muted" style="padding:32px;">No players found. Register some!</td></tr>
                        <?php else: ?>
                        <?php foreach ($players as $p): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <?= renderPlayerAvatar($p['POSITION'] ?? 'Athlete', $p['JERSEY_NO'] ?? '', 30) ?>
                                        </div>
                                        <div>
                                            <a href="#" class="player-name-link"><?= htmlspecialchars($p['P_NAME']) ?></a>
                                            <div style="font-size:0.75rem;color:var(--text-muted);display:flex;align-items:center;gap:8px;">
                                                <span><?= htmlspecialchars($p['POSITION'] ?? 'Athlete') ?></span>
                                                <?php if (!empty($p['HEIGHT'])): ?>
                                                    <span>• <?= $p['HEIGHT'] ?> cm</span>
                                                <?php endif; ?>
                                                <?php if (!empty($p['WEIGHT'])): ?>
                                                    <span>• <?= $p['WEIGHT'] ?> kg</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-sm"><?= htmlspecialchars($p['TEAMNAME']) ?></td>
                                <td class="text-center font-bold text-sm"><?= $p['JERSEY_NO'] ?? '—' ?></td>
                                <td class="text-sm text-muted"><?= $p['DOB'] ? date('d M Y', strtotime($p['DOB'])) : '—' ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                                        <button type="button" class="btn-action btn-action-edit" title="Edit Player"
                                                onclick='openEditPlayerModal(<?= json_encode([
                                                    "id"       => (int)$p["PLAYERID"],
                                                    "name"     => $p["P_NAME"],
                                                    "position" => $p["POSITION"] ?? "",
                                                    "jersey"   => (int)$p["JERSEY_NO"],
                                                    "height"   => $p["HEIGHT"] ?? "",
                                                    "weight"   => $p["WEIGHT"] ?? "",
                                                    "dob"      => $p["DOB"] ? date("Y-m-d", strtotime($p["DOB"])) : "",
                                                    "team_id"  => (int)$p["TEAMID"]
                                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <a href="?delete=<?= $p['PLAYERID'] ?>" class="btn-action btn-action-delete" title="Delete Player"
                                           onclick="return confirm('Are you sure you want to remove <?= htmlspecialchars(addslashes($p['P_NAME'])) ?>?')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span>Showing <?= count($players) ?> of <?= $totalPlayers ?> Players</span>
                    <div style="display:flex;gap:6px;">
                        <a href="#" class="page-btn">Previous</a>
                        <a href="#" class="page-btn">Next</a>
                    </div>
                </div>
            </div>

            <!-- Right: Add Player Form + Stats -->
            <div>
                <!-- Add New Player Form (Compact side design) -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header" style="background:var(--bg-table-header);">
                        <span class="card-title" style="font-size:0.95rem;font-weight:700;">Add New Player</span>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action_add" value="1">
                            
                            <div class="form-group" style="margin-bottom:12px;">
                                <label class="form-label-compact">Full Name *</label>
                                <input type="text" name="p_name" class="form-control" placeholder="e.g. Shakib Al Hasan" required>
                            </div>

                            <div class="form-group" style="margin-bottom:12px;">
                                <label class="form-label-compact">Position *</label>
                                <input type="text" name="position" class="form-control" list="posSuggestions" placeholder="e.g. Forward, Batsman" required>
                                <datalist id="posSuggestions">
                                    <option value="Forward">
                                    <option value="Striker">
                                    <option value="Winger">
                                    <option value="Midfielder">
                                    <option value="Attacking Midfielder">
                                    <option value="Defensive Midfielder">
                                    <option value="Defender">
                                    <option value="Center Back">
                                    <option value="Goalkeeper">
                                    <option value="Batsman">
                                    <option value="Opening Batsman">
                                    <option value="Middle Order Batsman">
                                    <option value="Fast Bowler">
                                    <option value="Spin Bowler">
                                    <option value="All-Rounder">
                                    <option value="Wicketkeeper">
                                    <option value="Point Guard">
                                    <option value="Shooting Guard">
                                    <option value="Center">
                                    <option value="Athlete">
                                </datalist>
                            </div>

                            <div class="form-row-2col">
                                <div>
                                    <label class="form-label-compact">Jersey # *</label>
                                    <input type="number" name="jersey_no" class="form-control" placeholder="10" min="1" max="99" required>
                                </div>
                                <div>
                                    <label class="form-label-compact">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control">
                                </div>
                            </div>

                            <div class="form-row-2col">
                                <div>
                                    <label class="form-label-compact">Height (cm)</label>
                                    <input type="number" name="height" class="form-control" placeholder="178" min="100" max="250">
                                </div>
                                <div>
                                    <label class="form-label-compact">Weight (kg)</label>
                                    <input type="number" name="weight" class="form-control" placeholder="74" min="30" max="180">
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label-compact">Team Selection *</label>
                                <select name="team_id_form" class="form-control" required>
                                    <option value="">Select a team</option>
                                    <?php foreach ($teams as $t): ?>
                                    <option value="<?= $t['TEAMID'] ?>" <?= $filterTeam == $t['TEAMID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['TEAMNAME']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary btn-full" style="display:flex;align-items:center;justify-content:center;gap:6px;font-weight:600;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Register Player
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Statistics -->
                <div class="card">
                    <div class="card-body" style="padding:0;">
                        <div class="quick-stats-header">Quick Statistics</div>
                        <div class="quick-stat-row">
                            <span>Active Players</span>
                            <span class="quick-stat-val"><?= number_format($totalPlayers) ?></span>
                        </div>
                        <div class="quick-stat-row">
                            <span>Teams Registered</span>
                            <span class="quick-stat-val"><?= $totalTeams ?></span>
                        </div>
                        <div class="quick-stat-row">
                            <span>Avg. Age</span>
                            <span class="quick-stat-val"><?= $avgAge ?></span>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- .player-layout -->

    </main>
</div>

<!-- Edit Player Modal -->
<div class="modal-overlay" id="editPlayerModal">
    <div class="modal-box">
        <div class="modal-header">
            <span style="font-weight:700;font-size:1.05rem;">Edit Player Details</span>
            <button type="button" class="btn btn-ghost btn-sm" onclick="closeEditPlayerModal()" style="font-size:1.1rem;line-height:1;">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action_edit" value="1">
            <input type="hidden" name="player_id" id="edit_player_id">

            <div class="modal-body">
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label-compact">Full Name *</label>
                    <input type="text" name="p_name" id="edit_p_name" class="form-control" required>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label-compact">Position *</label>
                    <input type="text" name="position" id="edit_position" class="form-control" list="posSuggestions" required>
                </div>

                <div class="form-row-2col">
                    <div>
                        <label class="form-label-compact">Jersey Number *</label>
                        <input type="number" name="jersey_no" id="edit_jersey_no" class="form-control" min="1" max="99" required>
                    </div>
                    <div>
                        <label class="form-label-compact">Date of Birth</label>
                        <input type="date" name="dob" id="edit_dob" class="form-control">
                    </div>
                </div>

                <div class="form-row-2col">
                    <div>
                        <label class="form-label-compact">Height (cm)</label>
                        <input type="number" name="height" id="edit_height" class="form-control" min="100" max="250">
                    </div>
                    <div>
                        <label class="form-label-compact">Weight (kg)</label>
                        <input type="number" name="weight" id="edit_weight" class="form-control" min="30" max="180">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:8px;">
                    <label class="form-label-compact">Assigned Team *</label>
                    <select name="team_id_form" id="edit_team_id" class="form-control" required>
                        <option value="">Select a team</option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= $t['TEAMID'] ?>"><?= htmlspecialchars($t['TEAMNAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeEditPlayerModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditPlayerModal(player) {
    document.getElementById('edit_player_id').value = player.id;
    document.getElementById('edit_p_name').value     = player.name || '';
    document.getElementById('edit_position').value   = player.position || '';
    document.getElementById('edit_jersey_no').value  = player.jersey || '';
    document.getElementById('edit_dob').value        = player.dob || '';
    document.getElementById('edit_height').value     = player.height || '';
    document.getElementById('edit_weight').value     = player.weight || '';
    document.getElementById('edit_team_id').value    = player.team_id || '';
    document.getElementById('editPlayerModal').style.display = 'flex';
}

function closeEditPlayerModal() {
    document.getElementById('editPlayerModal').style.display = 'none';
}

// Close on outside click
document.getElementById('editPlayerModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditPlayerModal();
});
</script>
<script src="<?= BASE_URL ?>js/main.js"></script>
</body>
</html>
