<?php
session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') { 
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit(); 
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';

$conn = getOracleConnection();
$successMsg = '';
$errorMsg   = '';

// ── POSITION OPTIONS (consistent with coach portal)
$positions = [
    'Goalkeeper','Defender','Midfielder','Forward','Striker','Winger',
    'Opening Batsman','Middle Order Batsman','All-Rounder','Wicketkeeper',
    'Fast Bowler','Spin Bowler','Point Guard','Shooting Guard','Small Forward',
    'Power Forward','Center','Athlete','Batsman','Bowler',
];

// ── GET ALL TEAMS ─────────────────────────────────────
$teams = oracleQuery($conn, "SELECT TEAMID, TEAMNAME FROM TEAM ORDER BY TEAMNAME");

// ── Handle Add Player ─────────────────────────────────
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'add_player') {
    $pName    = trim($_POST['p_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $jerseyNo = intval($_POST['jersey_no'] ?? 0);
    $teamId   = intval($_POST['team_id'] ?? 0);
    $height   = intval($_POST['height'] ?? 0) ?: null;
    $weight   = intval($_POST['weight'] ?? 0) ?: null;
    $dob      = trim($_POST['dob'] ?? '') ?: null;

    if (empty($pName) || empty($position) || $jerseyNo <= 0 || $teamId <= 0) {
        $errorMsg = 'Please enter Player Name, Position, Team, and a valid Jersey Number.';
    } else {
        // Check duplicate jersey number within team
        $dup = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER WHERE TEAMID = :tid AND (JERSEYNO = :jno OR JERSEY_NO = :jno2)", [
            'tid'  => $teamId,
            'jno'  => $jerseyNo,
            'jno2' => $jerseyNo
        ]);
        if (($dup[0]['CNT'] ?? 0) > 0) {
            $errorMsg = "Jersey #{$jerseyNo} is already assigned to another player in this team.";
        } else {
            $ok = oracleExecute($conn, "
                INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, Height, Weight, DOB, TeamID)
                VALUES (seq_player.NEXTVAL, :pname, :pos, :jno, :jno2, :h, :w, :dob, :tid)
            ", [
                'pname' => $pName,
                'pos'   => $position,
                'jno'   => $jerseyNo,
                'jno2'  => $jerseyNo,
                'h'     => $height,
                'w'     => $weight,
                'dob'   => $dob,
                'tid'   => $teamId
            ]);
            if ($ok) {
                $successMsg = "Player <strong>" . esc($pName) . "</strong> (#{$jerseyNo}) has been added successfully!";
            } else {
                $errorMsg = "Could not add player. Please check inputs and try again.";
            }
        }
    }
}

// ── Handle Edit Player ────────────────────────────────
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'edit_player') {
    $playerId = intval($_POST['player_id'] ?? 0);
    $pName    = trim($_POST['p_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $jerseyNo = intval($_POST['jersey_no'] ?? 0);
    $teamId   = intval($_POST['team_id'] ?? 0);
    $height   = intval($_POST['height'] ?? 0) ?: null;
    $weight   = intval($_POST['weight'] ?? 0) ?: null;
    $dob      = trim($_POST['dob'] ?? '') ?: null;

    if ($playerId <= 0 || empty($pName) || empty($position) || $jerseyNo <= 0) {
        $errorMsg = 'Please provide valid player details and a valid jersey number.';
    } else {
        // Check player exists
        $exists = oracleQuery($conn, "SELECT PlayerID FROM PLAYER WHERE PlayerID = :pid", ['pid' => $playerId]);
        if (empty($exists)) {
            $errorMsg = 'Player not found.';
        } else {
            // Check duplicate jersey number excluding this player
            $dup = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER WHERE TEAMID = :tid AND (JERSEYNO = :jno OR JERSEY_NO = :jno2) AND PlayerID != :pid", [
                'tid'  => $teamId,
                'jno'  => $jerseyNo,
                'jno2' => $jerseyNo,
                'pid'  => $playerId
            ]);
            if (($dup[0]['CNT'] ?? 0) > 0) {
                $errorMsg = "Jersey #{$jerseyNo} is already assigned to another player in this team.";
            } else {
                $ok = oracleExecute($conn, "
                    UPDATE PLAYER SET 
                        P_Name    = :pname,
                        Position  = :pos,
                        JerseyNo  = :jno,
                        Jersey_No = :jno2,
                        Height    = :h,
                        Weight    = :w,
                        DOB       = :dob,
                        TeamID    = :tid
                    WHERE PlayerID = :pid
                ", [
                    'pname' => $pName,
                    'pos'   => $position,
                    'jno'   => $jerseyNo,
                    'jno2'  => $jerseyNo,
                    'h'     => $height,
                    'w'     => $weight,
                    'dob'   => $dob,
                    'tid'   => $teamId,
                    'pid'   => $playerId
                ]);
                if ($ok) {
                    $successMsg = "Player <strong>" . esc($pName) . "</strong> has been updated successfully!";
                } else {
                    $errorMsg = "Could not update player. Please check inputs and try again.";
                }
            }
        }
    }
}

// ── Fetch all players ─────────────────────────────────
$players = oracleQuery($conn, "SELECT P.*, T.TEAMNAME FROM PLAYER P JOIN TEAM T ON P.TEAMID=T.TEAMID ORDER BY T.TEAMNAME, P.JERSEY_NO");
$currentPage = 'players';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Records — Staff Portal — STMS</title>
    <meta name="description" content="Staff portal player records management. Add or update player information for all teams.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
    <style>
        .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;align-items:center;justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:var(--bg-card);border-radius:var(--radius-xl);padding:32px;width:520px;max-width:95vw;box-shadow:var(--shadow-lg);animation:slideUp .2s ease; }
        @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
        .modal-title { font-size:1.1rem;font-weight:800;color:var(--text-primary);margin-bottom:20px; }
        .form-row { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
        .btn-action { padding:5px 8px;border:none;border-radius:var(--radius);cursor:pointer;font-size:0.78rem;font-weight:700;transition:all .15s; }
        .btn-edit { background:#e8f5e9;color:#1b5e20; }
        .btn-edit:hover { background:#a5d6a7;color:#0a3d0a; }
        .row-avatar { width:34px;height:34px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    </style>
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
                    <input type="text" id="playerSearch" placeholder="Search players..." oninput="searchTable(this.value)">
                </div>
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'ST', 0, 2)) ?></div>
            </div>
        </div>

        <div class="page-header">
            <div class="breadcrumb">Staff / <span>Player Records</span></div>
            <div class="page-header-row">
                <div>
                    <h1 style="font-size:1.25rem;">Player Records</h1>
                    <p class="page-subtitle"><?= count($players) ?> players registered across all teams.</p>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add New Player
                </button>
            </div>
        </div>

        <?php if ($successMsg): ?>
        <div class="alert alert-success" style="margin-bottom:16px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> <?= $successMsg ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        <div class="alert alert-danger" style="margin-bottom:16px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> <?= $errorMsg ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="table-wrap">
                <table id="playerTable">
                    <thead>
                        <tr>
                            <th>Player Name</th>
                            <th>Team</th>
                            <th>Position</th>
                            <th>Jersey</th>
                            <th>DOB</th>
                            <th>Height</th>
                            <th>Weight</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($players)): ?>
                        <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">No player records found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($players as $p): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="row-avatar">
                                    <?= renderPlayerAvatar($p['POSITION'], $p['JERSEY_NO'], 30) ?>
                                </div>
                                <strong><?= esc($p['P_NAME']) ?></strong>
                            </div>
                        </td>
                        <td class="text-sm"><?= esc($p['TEAMNAME']) ?></td>
                        <td><span class="badge badge-scheduled" style="font-size:0.72rem;"><?= esc($p['POSITION']) ?></span></td>
                        <td class="text-center font-bold"><?= $p['JERSEY_NO'] ?></td>
                        <td class="text-sm"><?= $p['DOB'] ? date('d M Y', strtotime($p['DOB'])) : '—' ?></td>
                        <td class="text-sm"><?= $p['HEIGHT'] ? $p['HEIGHT'].' cm' : '—' ?></td>
                        <td class="text-sm"><?= $p['WEIGHT'] ? $p['WEIGHT'].' kg' : '—' ?></td>
                        <td>
                            <button class="btn-action btn-edit" title="Edit Player"
                                onclick='openEditModal(<?= json_encode([
                                    "id"       => (int)$p["PLAYERID"],
                                    "name"     => $p["P_NAME"],
                                    "position" => $p["POSITION"],
                                    "jersey"   => (int)$p["JERSEY_NO"],
                                    "teamId"   => (int)$p["TEAMID"],
                                    "teamName" => $p["TEAMNAME"],
                                    "height"   => $p["HEIGHT"] ? (int)$p["HEIGHT"] : "",
                                    "weight"   => $p["WEIGHT"] ? (int)$p["WEIGHT"] : "",
                                    "dob"      => $p["DOB"] ? date("Y-m-d", strtotime($p["DOB"])) : "",
                                ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>
                                ✏️ Edit
                            </button>
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

<!-- Add Player Modal -->
<div class="modal-overlay" id="addPlayerModal">
    <div class="modal-box">
        <div class="modal-title">➕ Add New Player</div>
        <form method="POST">
            <input type="hidden" name="action" value="add_player">
            <div class="form-row" style="margin-bottom:12px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Full Name <span style="color:#e53e3e;">*</span></label>
                    <input type="text" name="p_name" class="form-control" placeholder="e.g. Tamim Iqbal" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Team <span style="color:#e53e3e;">*</span></label>
                    <select name="team_id" class="form-control" required>
                        <option value="">Select Team...</option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= $t['TEAMID'] ?>"><?= esc($t['TEAMNAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row" style="margin-bottom:12px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Position <span style="color:#e53e3e;">*</span></label>
                    <select name="position" class="form-control" required>
                        <option value="">Select Position...</option>
                        <?php foreach ($positions as $pos): ?>
                        <option value="<?= esc($pos) ?>"><?= esc($pos) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Jersey No. <span style="color:#e53e3e;">*</span></label>
                    <input type="number" name="jersey_no" class="form-control" placeholder="e.g. 10" min="1" max="99" required>
                </div>
            </div>
            <div class="form-row" style="margin-bottom:12px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Height (cm)</label>
                    <input type="number" name="height" class="form-control" placeholder="e.g. 175" min="100" max="230">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight" class="form-control" placeholder="e.g. 70" min="40" max="150">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label>Date of Birth</label>
                <input type="date" name="dob" class="form-control">
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Player</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Player Modal -->
<div class="modal-overlay" id="editPlayerModal">
    <div class="modal-box">
        <div class="modal-title">✏️ Edit Player</div>
        <form method="POST" id="editPlayerForm">
            <input type="hidden" name="action" value="edit_player">
            <input type="hidden" name="player_id" id="edit_player_id">
            <div class="form-row" style="margin-bottom:12px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Full Name <span style="color:#e53e3e;">*</span></label>
                    <input type="text" name="p_name" id="edit_p_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Team <span style="color:#e53e3e;">*</span></label>
                    <select name="team_id" id="edit_team_id" class="form-control" required>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= $t['TEAMID'] ?>"><?= esc($t['TEAMNAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row" style="margin-bottom:12px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Position <span style="color:#e53e3e;">*</span></label>
                    <select name="position" id="edit_position" class="form-control" required>
                        <?php foreach ($positions as $pos): ?>
                        <option value="<?= esc($pos) ?>"><?= esc($pos) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Jersey No. <span style="color:#e53e3e;">*</span></label>
                    <input type="number" name="jersey_no" id="edit_jersey_no" class="form-control" min="1" max="99" required>
                </div>
            </div>
            <div class="form-row" style="margin-bottom:12px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Height (cm)</label>
                    <input type="number" name="height" id="edit_height" class="form-control" min="100" max="230">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight" id="edit_weight" class="form-control" min="40" max="150">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label>Date of Birth</label>
                <input type="date" name="dob" id="edit_dob" class="form-control">
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>js/main.js"></script>
<script>
// ── Add Modal ──────────────────────────────────────
function openAddModal() {
    document.getElementById('addPlayerModal').classList.add('open');
}
function closeAddModal() {
    document.getElementById('addPlayerModal').classList.remove('open');
}
document.getElementById('addPlayerModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});

// ── Edit Modal ─────────────────────────────────────
function openEditModal(data) {
    document.getElementById('edit_player_id').value  = data.id;
    document.getElementById('edit_p_name').value     = data.name;
    document.getElementById('edit_position').value   = data.position;
    document.getElementById('edit_jersey_no').value  = data.jersey;
    document.getElementById('edit_team_id').value    = data.teamId;
    document.getElementById('edit_height').value     = data.height || '';
    document.getElementById('edit_weight').value     = data.weight || '';
    document.getElementById('edit_dob').value        = data.dob   || '';
    document.getElementById('editPlayerModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editPlayerModal').classList.remove('open');
}
document.getElementById('editPlayerModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// ── Search ─────────────────────────────────────────
function searchTable(q) {
    const rows = document.querySelectorAll('#playerTable tbody tr');
    const lq = q.toLowerCase();
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(lq) ? '' : 'none';
    });
}

// ── Auto-open modal after error ────────────────────
<?php if ($errorMsg && isset($_POST['action'])): ?>
<?php if ($_POST['action'] === 'add_player'): ?>
document.addEventListener('DOMContentLoaded', () => openAddModal());
<?php elseif ($_POST['action'] === 'edit_player'): ?>
document.addEventListener('DOMContentLoaded', () => openEditModal({
    id:       <?= (int)($_POST['player_id'] ?? 0) ?>,
    name:     <?= json_encode($_POST['p_name'] ?? '') ?>,
    position: <?= json_encode($_POST['position'] ?? '') ?>,
    jersey:   <?= (int)($_POST['jersey_no'] ?? 0) ?>,
    teamId:   <?= (int)($_POST['team_id'] ?? 0) ?>,
    height:   <?= (int)($_POST['height'] ?? 0) ?: '""' ?>,
    weight:   <?= (int)($_POST['weight'] ?? 0) ?: '""' ?>,
    dob:      <?= json_encode($_POST['dob'] ?? '') ?>,
}));
<?php endif; ?>
<?php endif; ?>
</script>
</body>
</html>
