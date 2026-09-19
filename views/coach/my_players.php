<?php
session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coach') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); 
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';

$conn       = getOracleConnection();
$coachId    = $_SESSION['user_id'] ?? null;
$teamId     = $_SESSION['team_id'] ?? null;
$successMsg = '';
$errorMsg   = '';

// ── Handle Add Player ─────────────────────────────────
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'add_player') {
    $pName    = trim($_POST['p_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $jerseyNo = intval($_POST['jersey_no'] ?? 0);
    $height   = intval($_POST['height'] ?? 0) ?: null;
    $weight   = intval($_POST['weight'] ?? 0) ?: null;
    $dob      = trim($_POST['dob'] ?? '') ?: null;

    if (empty($pName) || empty($position) || $jerseyNo <= 0) {
        $errorMsg = 'Please enter Player Name, Position, and a valid Jersey Number.';
    } elseif (!$teamId) {
        $errorMsg = 'No team assigned to your coach account.';
    } else {
        // Check duplicate jersey number
        $dup = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER WHERE TEAMID = :tid AND (JERSEYNO = :jno OR JERSEY_NO = :jno2)", [
            'tid'  => $teamId,
            'jno'  => $jerseyNo,
            'jno2' => $jerseyNo
        ]);

        if (($dup[0]['CNT'] ?? 0) > 0) {
            $errorMsg = "Jersey #{$jerseyNo} is already assigned to another player in your team.";
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
                $successMsg = "Player <strong>" . esc($pName) . "</strong> (#{$jerseyNo}) has been added to your roster!";
            } else {
                $errorMsg = "Could not add player. Please check inputs.";
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
    $height   = intval($_POST['height'] ?? 0) ?: null;
    $weight   = intval($_POST['weight'] ?? 0) ?: null;
    $dob      = trim($_POST['dob'] ?? '') ?: null;

    if ($playerId <= 0 || empty($pName) || empty($position) || $jerseyNo <= 0) {
        $errorMsg = 'Please provide valid player details and a valid jersey number.';
    } elseif (!$teamId) {
        $errorMsg = 'No team assigned to your coach account.';
    } else {
        // Ensure player belongs to coach's team
        $exists = oracleQuery($conn, "SELECT PlayerID FROM PLAYER WHERE PlayerID = :pid AND TeamID = :tid", [
            'pid' => $playerId,
            'tid' => $teamId
        ]);

        if (empty($exists)) {
            $errorMsg = 'Unauthorized: Player not found in your team roster.';
        } else {
            // Check duplicate jersey number excluding this player
            $dup = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER WHERE TEAMID = :tid AND (JERSEYNO = :jno OR JERSEY_NO = :jno2) AND PlayerID != :pid", [
                'tid'  => $teamId,
                'jno'  => $jerseyNo,
                'jno2' => $jerseyNo,
                'pid'  => $playerId
            ]);

            if (($dup[0]['CNT'] ?? 0) > 0) {
                $errorMsg = "Jersey #{$jerseyNo} is already assigned to another player in your team.";
            } else {
                $ok = oracleExecute($conn, "
                    UPDATE PLAYER 
                    SET P_NAME = :pname, 
                        POSITION = :pos, 
                        JERSEYNO = :jno, 
                        JERSEY_NO = :jno2, 
                        HEIGHT = :h, 
                        WEIGHT = :w, 
                        DOB = :dob
                    WHERE PlayerID = :pid AND TeamID = :tid
                ", [
                    'pname' => $pName,
                    'pos'   => $position,
                    'jno'   => $jerseyNo,
                    'jno2'  => $jerseyNo,
                    'h'     => $height,
                    'w'     => $weight,
                    'dob'   => $dob,
                    'pid'   => $playerId,
                    'tid'   => $teamId
                ]);

                if ($ok) {
                    $successMsg = "Player <strong>" . esc($pName) . "</strong> (#{$jerseyNo}) has been updated successfully!";
                } else {
                    $errorMsg = "Could not update player. Please try again.";
                }
            }
        }
    }
}

// ── Handle Delete Player ──────────────────────────────
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'delete_player') {
    $playerId = intval($_POST['player_id'] ?? 0);

    if ($playerId <= 0) {
        $errorMsg = 'Invalid player selection.';
    } elseif (!$teamId) {
        $errorMsg = 'No team assigned to your coach account.';
    } else {
        $playerRow = oracleQuery($conn, "SELECT P_NAME FROM PLAYER WHERE PlayerID = :pid AND TeamID = :tid", [
            'pid' => $playerId,
            'tid' => $teamId
        ]);

        if (empty($playerRow)) {
            $errorMsg = 'Unauthorized: Player not found in your team roster.';
        } else {
            $pName = $playerRow[0]['P_NAME'] ?? $playerRow[0]['P_Name'] ?? 'Player';
            
            // Delete contact records first to maintain data integrity
            oracleExecute($conn, "DELETE FROM PLAYER_PHONE WHERE PlayerID = :pid", ['pid' => $playerId]);
            
            $ok = oracleExecute($conn, "DELETE FROM PLAYER WHERE PlayerID = :pid AND TeamID = :tid", [
                'pid' => $playerId,
                'tid' => $teamId
            ]);

            if ($ok) {
                $successMsg = "Player <strong>" . esc($pName) . "</strong> has been removed from your team roster.";
            } else {
                $errorMsg = "Could not remove player. Please try again.";
            }
        }
    }
}

$players = $teamId ? oracleQuery($conn, "SELECT * FROM PLAYER WHERE TEAMID = :tid ORDER BY COALESCE(JERSEY_NO, JERSEYNO, 999), P_NAME", ['tid' => $teamId]) : [];
$currentPage = 'my_players';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Players — Coach Portal — STMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/forms.css">
    <style>
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
        .player-card-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }
    </style>
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
            <div class="breadcrumb">Coach / <span>My Players</span></div>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-size:1.25rem;">My Players</h1>
                    <p class="page-subtitle">Detailed view of all players on your roster (<?= count($players) ?> active players).</p>
                </div>
                <button type="button" class="btn btn-primary" onclick="toggleAddPlayerForm()">
                    + Add New Player
                </button>
            </div>
        </div>

        <?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg):   ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

        <!-- Add Player Form Card (Collapsible) -->
        <div class="card" id="addPlayerCard" style="display:none;margin-bottom:24px;border:1px solid var(--border);">
            <div class="card-header" style="background:var(--bg-table-header);">
                <span class="card-title">Add New Player to Roster</span>
                <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAddPlayerForm()">✕ Close</button>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_player">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Player Full Name *</label>
                            <input type="text" name="p_name" class="form-control" placeholder="e.g. Shakib Al Hasan, Lionel Messi" required>
                        </div>
                        <div class="form-group">
                            <label>Position *</label>
                            <input type="text" name="position" id="add_position" class="form-control" list="positionSuggestions" placeholder="e.g. Goalkeeper, Defender, Midfielder, Forward, Batsman" required>
                            <datalist id="positionSuggestions">
                                <option value="Goalkeeper">
                                <option value="Defender">
                                <option value="Center Back">
                                <option value="Left Back">
                                <option value="Right Back">
                                <option value="Midfielder">
                                <option value="Defensive Midfielder">
                                <option value="Attacking Midfielder">
                                <option value="Winger">
                                <option value="Forward">
                                <option value="Striker">
                                <option value="Batsman">
                                <option value="Opening Batsman">
                                <option value="Middle Order Batsman">
                                <option value="Fast Bowler">
                                <option value="Spin Bowler">
                                <option value="Wicketkeeper">
                                <option value="All-Rounder">
                            </datalist>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jersey Number *</label>
                            <input type="number" name="jersey_no" id="add_jersey_no" class="form-control" placeholder="e.g. 10" min="1" max="99" required>
                        </div>
                        <div class="form-group">
                            <label>Height (cm)</label>
                            <input type="number" name="height" class="form-control" placeholder="e.g. 178">
                        </div>
                        <div class="form-group">
                            <label>Weight (kg)</label>
                            <input type="number" name="weight" class="form-control" placeholder="e.g. 74">
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" class="form-control">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                        <button type="button" class="btn btn-outline" onclick="toggleAddPlayerForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Player</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Player Grid Cards -->
        <?php if (empty($players)): ?>
        <div class="alert alert-warning">No players in your team yet. Click "+ Add New Player" above to register your first player.</div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px;">
            <?php foreach ($players as $p): 
                $pid    = $p['PLAYERID'] ?? $p['PlayerID'] ?? $p['playerid'] ?? 0;
                $pName  = $p['P_NAME'] ?? $p['P_Name'] ?? $p['p_name'] ?? '';
                $pos    = $p['POSITION'] ?? $p['Position'] ?? $p['position'] ?? '';
                $jersey = $p['JERSEY_NO'] ?? $p['JERSEYNO'] ?? $p['JerseyNo'] ?? '';
                $height = $p['HEIGHT'] ?? $p['Height'] ?? $p['height'] ?? '';
                $weight = $p['WEIGHT'] ?? $p['Weight'] ?? $p['weight'] ?? '';
                $dob    = $p['DOB'] ?? $p['Dob'] ?? $p['dob'] ?? '';
            ?>
            <div class="card" style="padding:0;overflow:hidden;border:1px solid var(--border);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <div style="background:var(--primary);padding:22px 18px 18px;text-align:center;position:relative;">
                    <div style="margin:0 auto 10px;display:flex;justify-content:center;">
                        <?= renderPlayerAvatar($pos, $jersey, 72) ?>
                    </div>
                    <div style="font-weight:700;color:#fff;font-size:1.05rem;line-height:1.3;"><?= htmlspecialchars($pName) ?></div>
                    <span class="badge" style="background:rgba(255,255,255,0.22);color:#fff;margin-top:6px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;"><?= htmlspecialchars($pos) ?></span>
                </div>
                <div style="padding:16px;">
                    <div style="display:flex;justify-content:space-between;font-size:0.82rem;color:var(--text-muted);margin-bottom:8px;">
                        <span>Height</span><span class="font-semibold text-primary"><?= $height ? htmlspecialchars($height).' cm' : '—' ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.82rem;color:var(--text-muted);margin-bottom:8px;">
                        <span>Weight</span><span class="font-semibold text-primary"><?= $weight ? htmlspecialchars($weight).' kg' : '—' ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.82rem;color:var(--text-muted);">
                        <span>Date of Birth</span><span><?= $dob ? date('d M Y', strtotime($dob)) : '—' ?></span>
                    </div>
                    
                    <div class="player-card-actions">
                        <button type="button" class="btn-action btn-action-edit" title="Edit Player"
                                onclick='openEditPlayerModal(<?= json_encode([
                                    "id"       => (int)$pid,
                                    "name"     => $pName,
                                    "position" => $pos,
                                    "jersey"   => (int)$jersey,
                                    "height"   => $height ? (int)$height : "",
                                    "weight"   => $weight ? (int)$weight : "",
                                    "dob"      => $dob ? date("Y-m-d", strtotime($dob)) : ""
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button type="button" class="btn-action btn-action-delete" title="Remove Player"
                                onclick="confirmDeletePlayer(<?= (int)$pid ?>, <?= htmlspecialchars(json_encode($pName), ENT_QUOTES, 'UTF-8') ?>)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Edit Player Modal -->
<div id="editPlayerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:var(--radius-xl);padding:28px;width:520px;max-width:95vw;box-shadow:var(--shadow-lg);max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border);">
            <h2 style="font-size:1.15rem;font-weight:700;">Edit Player Details</h2>
            <button type="button" id="closeEditPlayerModal" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.5rem;line-height:1;">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="edit_player">
            <input type="hidden" name="player_id" id="edit_player_id" value="">
            <div class="form-row">
                <div class="form-group">
                    <label>Player Full Name *</label>
                    <input type="text" name="p_name" id="edit_p_name" class="form-control" placeholder="e.g. Shakib Al Hasan" required>
                </div>
                <div class="form-group">
                    <label>Position *</label>
                    <input type="text" name="position" id="edit_position" class="form-control" list="positionSuggestions" placeholder="e.g. Forward, Midfielder, Goalkeeper, Batsman" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Jersey Number *</label>
                    <input type="number" name="jersey_no" id="edit_jersey_no" class="form-control" min="1" max="99" required>
                </div>
                <div class="form-group">
                    <label>Height (cm)</label>
                    <input type="number" name="height" id="edit_height" class="form-control" placeholder="e.g. 178">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight" id="edit_weight" class="form-control" placeholder="e.g. 74">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" id="edit_dob" class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" id="cancelEditPlayerModal" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Player Confirmation Modal -->
<div id="deletePlayerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:var(--radius-xl);padding:32px;width:440px;max-width:95vw;box-shadow:var(--shadow-lg);">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
            <div style="width:44px;height:44px;border-radius:50%;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <h2 style="font-size:1.15rem;font-weight:700;color:var(--text-main);margin:0;">Remove Player</h2>
                <p style="font-size:0.85rem;color:var(--text-muted);margin:3px 0 0;">This action cannot be undone.</p>
            </div>
        </div>
        <p style="font-size:0.92rem;color:var(--text-main);line-height:1.5;margin-bottom:16px;">
            Are you sure you want to remove <strong id="deletePlayerName" style="color:#b91c1c;"></strong> from your team roster?
        </p>
        <p style="font-size:0.82rem;color:var(--text-muted);background:var(--bg-body);padding:8px 12px;border-radius:6px;margin-bottom:20px;">
            ℹ️ This will safely remove the player and any contact phone entries from your squad.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="delete_player">
            <input type="hidden" name="player_id" id="delete_player_id" value="">
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" id="cancelDeletePlayerModal" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn" style="background:#dc2626;color:#fff;border:none;">Remove Player</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>js/main.js"></script>
<script>
function toggleAddPlayerForm() {
    const card = document.getElementById('addPlayerCard');
    if (card.style.display === 'none' || !card.style.display) {
        card.style.display = 'block';
        card.scrollIntoView({ behavior: 'smooth' });
    } else {
        card.style.display = 'none';
    }
}

// Edit Modal
const editPlayerModal    = document.getElementById('editPlayerModal');
const closeEditPlayerBtn = document.getElementById('closeEditPlayerModal');
const cancelEditPlayerBtn= document.getElementById('cancelEditPlayerModal');

function openEditPlayerModal(data) {
    document.getElementById('edit_player_id').value = data.id;
    document.getElementById('edit_p_name').value    = data.name || '';
    document.getElementById('edit_position').value  = data.position || '';
    document.getElementById('edit_jersey_no').value = data.jersey || '';
    document.getElementById('edit_height').value    = data.height || '';
    document.getElementById('edit_weight').value    = data.weight || '';
    document.getElementById('edit_dob').value       = data.dob || '';

    editPlayerModal.style.display = 'flex';
}

function closeEditPlayerModal() {
    if (editPlayerModal) editPlayerModal.style.display = 'none';
}

if (closeEditPlayerBtn)   closeEditPlayerBtn.addEventListener('click', closeEditPlayerModal);
if (cancelEditPlayerBtn)  cancelEditPlayerBtn.addEventListener('click', closeEditPlayerModal);
if (editPlayerModal)      editPlayerModal.addEventListener('click', e => { if (e.target === editPlayerModal) closeEditPlayerModal(); });

// Delete Modal
const deletePlayerModal     = document.getElementById('deletePlayerModal');
const cancelDeletePlayerBtn = document.getElementById('cancelDeletePlayerModal');

function confirmDeletePlayer(id, name) {
    document.getElementById('delete_player_id').value = id;
    document.getElementById('deletePlayerName').textContent = name;
    deletePlayerModal.style.display = 'flex';
}

function closeDeletePlayerModal() {
    if (deletePlayerModal) deletePlayerModal.style.display = 'none';
}

if (cancelDeletePlayerBtn) cancelDeletePlayerBtn.addEventListener('click', closeDeletePlayerModal);
if (deletePlayerModal)     deletePlayerModal.addEventListener('click', e => { if (e.target === deletePlayerModal) closeDeletePlayerModal(); });

// Keyboard escape listener
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeEditPlayerModal();
        closeDeletePlayerModal();
    }
});
</script>
</body>
</html>
