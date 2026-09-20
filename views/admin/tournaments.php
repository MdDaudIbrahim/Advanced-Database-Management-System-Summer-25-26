<?php
// views/admin/tournaments.php
// Figma: "Tournaments - Sports Tournament Management System.png"

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn = getOracleConnection();

// ── Flash Messages (PRG Pattern) ──────────────────
$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Handle Delete Tournament ──────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_delete'])) {
    $delId = intval($_POST['tournament_id'] ?? 0);
    if ($delId > 0) {
        // Delete related payments and tickets for matches in this tournament
        $mRows = oracleQuery($conn, "SELECT MATCHID FROM MATCHES WHERE TOURNAMENTID = :tid", ['tid' => $delId]);
        foreach ($mRows as $m) {
            $mid = $m['MATCHID'];
            oracleExecute($conn, "DELETE FROM TICKET WHERE MATCHID = :mid", ['mid' => $mid]);
        }
        // Delete matches
        oracleExecute($conn, "DELETE FROM MATCHES WHERE TOURNAMENTID = :tid", ['tid' => $delId]);
        // Disassociate teams
        oracleExecute($conn, "UPDATE TEAM SET TOURNAMENTID = NULL WHERE TOURNAMENTID = :tid", ['tid' => $delId]);
        // Delete team registrations
        oracleExecute($conn, "DELETE FROM REGISTRATION WHERE TOURNAMENTID = :tid", ['tid' => $delId]);
        // Delete tournament
        $ok = oracleExecute($conn, "DELETE FROM TOURNAMENT WHERE TOURNAMENTID = :tid", ['tid' => $delId]);
        if ($ok) {
            $_SESSION['flash_success'] = "Tournament #{$delId} and its associated matches were deleted successfully.";
        } else {
            $_SESSION['flash_error'] = "Could not delete tournament #{$delId}.";
        }
    }
    header('Location: ' . BASE_URL . 'views/admin/tournaments.php');
    exit();
}

// ── Handle Update Tournament ──────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update'])) {
    $editId    = intval($_POST['tournament_id'] ?? 0);
    $name      = trim($_POST['t_name'] ?? '');
    $sport     = trim($_POST['sport_type'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate   = trim($_POST['end_date'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $prize     = intval($_POST['prize_money'] ?? 0);
    $maxTeams  = intval($_POST['max_teams'] ?? 8);

    if ($editId > 0 && !empty($name) && !empty($sport) && !empty($startDate) && !empty($endDate)) {
        $sql = "UPDATE TOURNAMENT 
                SET T_NAME = :tname, 
                    SPORT_TYPE = :sport, 
                    STARTDATE = TO_DATE(:sd, 'YYYY-MM-DD'), 
                    ENDDATE = TO_DATE(:ed, 'YYYY-MM-DD'), 
                    LOCATION = :loc, 
                    MAX_TEAMS = :maxteams, 
                    PRIZEMONEY = :prize 
                WHERE TOURNAMENTID = :tid";
        $ok = oracleExecute($conn, $sql, [
            'tname'    => $name,
            'sport'    => $sport,
            'sd'       => $startDate,
            'ed'       => $endDate,
            'loc'      => $location,
            'maxteams' => $maxTeams,
            'prize'    => $prize,
            'tid'      => $editId
        ]);
        if ($ok) {
            $_SESSION['flash_success'] = "Tournament '{$name}' updated successfully!";
        } else {
            $_SESSION['flash_error'] = "Failed to update tournament. Please check the values.";
        }
    } else {
        $_SESSION['flash_error'] = "Please fill in all required fields to update.";
    }
    header('Location: ' . BASE_URL . 'views/admin/tournaments.php');
    exit();
}

// ── Handle Create Tournament ──────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_create'])) {
    $name      = trim($_POST['t_name'] ?? '');
    $sport     = trim($_POST['sport_type'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate   = trim($_POST['end_date'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $prize     = intval($_POST['prize_money'] ?? 0);
    $maxTeams  = intval($_POST['max_teams'] ?? 8);

    if (!empty($name) && !empty($sport) && !empty($startDate) && !empty($endDate)) {
        $sql = "INSERT INTO TOURNAMENT (TournamentID, T_Name, StartDate, EndDate, Sport_Type, Location, Max_Teams, PrizeMoney)
                VALUES (seq_tournament.NEXTVAL, :tname, TO_DATE(:sd, 'YYYY-MM-DD'), TO_DATE(:ed, 'YYYY-MM-DD'), :sport, :loc, :maxteams, :prize)";
        $ok = oracleExecute($conn, $sql, [
            'tname'    => $name,
            'sd'       => $startDate,
            'ed'       => $endDate,
            'sport'    => $sport,
            'loc'      => $location,
            'maxteams' => $maxTeams,
            'prize'    => $prize
        ]);
        if ($ok) {
            $_SESSION['flash_success'] = 'Tournament created successfully!';
        } else {
            $_SESSION['flash_error'] = 'Could not create tournament. Please try again.';
        }
    } else {
        $_SESSION['flash_error'] = 'Please fill all required fields.';
    }
    header('Location: ' . BASE_URL . 'views/admin/tournaments.php');
    exit();
}

// ── KPI Stats ──────────────────────────────────────
$kpiTotal  = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TOURNAMENT")[0]['CNT'] ?? 0;
$kpiActive = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TOURNAMENT WHERE SYSDATE BETWEEN STARTDATE AND ENDDATE")[0]['CNT'] ?? 0;
$kpiPrize  = oracleQuery($conn, "SELECT NVL(SUM(PRIZEMONEY),0) AS TOTAL FROM TOURNAMENT")[0]['TOTAL'] ?? 0;
$kpiParts  = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM PLAYER")[0]['CNT'] ?? 0;

// ── Fetch Tournaments ──────────────────────────────
$tournaments = oracleQuery($conn, "
    SELECT T.TOURNAMENTID, T.T_NAME, T.STARTDATE, T.ENDDATE, T.SPORT_TYPE,
           T.LOCATION, T.MAX_TEAMS, NVL(T.PRIZEMONEY, 0) AS PRIZE
    FROM TOURNAMENT T
    ORDER BY T.STARTDATE DESC
");

$currentPage = 'tournaments';
$pageTitle   = 'Tournament Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournaments — Sports Tournament Management System</title>
    <meta name="description" content="Create, organize, and monitor all active and upcoming sports tournaments.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
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
                <input type="text" placeholder="Search tournaments, teams..." id="searchInput">
            </div>
            <div class="topbar-right">
                <a href="<?= BASE_URL ?>controllers/AuthController.php?action=logout" class="icon-btn" title="Logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-row">
                <div>
                    <h1 style="font-size:1.4rem;">Tournament Management</h1>
                    <p class="page-subtitle">Create, organize, and monitor active and upcoming sports events across Bangladesh.</p>
                </div>
                <button class="btn btn-primary" id="openCreateModal" style="display:inline-flex;align-items:center;gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Create Tournament
                </button>
            </div>
        </div>

        <?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg):   ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

        <!-- KPI Row -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Total Tournaments</span>
                    <div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></div>
                </div>
                <div class="kpi-value"><?= $kpiTotal ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Active Events</span>
                    <div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></div>
                </div>
                <div class="kpi-value text-green"><?= $kpiActive ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Total Prize Pool</span>
                    <div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                </div>
                <div class="kpi-value" style="font-size:1.5rem;">৳<?= number_format($kpiPrize) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Participants</span>
                    <div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                </div>
                <div class="kpi-value"><?= number_format($kpiParts) ?></div>
            </div>
        </div>

        <!-- Tournaments Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Current Tournaments</span>
                <div style="display:flex;gap:8px;">
                    <a href="#" class="btn btn-outline btn-sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
                        Filter
                    </a>
                    <a href="#" class="btn btn-outline btn-sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="tournamentsTable">
                    <thead>
                        <tr>
                            <th>Tournament Name</th>
                            <th>Season</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Prize (BDT)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tournaments)): ?>
                        <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No tournaments yet. Create your first one!</td></tr>
                    <?php else: ?>
                    <?php foreach ($tournaments as $t):
                        $now   = time();
                        $start = strtotime($t['STARTDATE']);
                        $end   = strtotime($t['ENDDATE']);
                        // Determine season from month
                        $startMonth = (int)date('n', $start);
                        $season = ($startMonth >= 11 || $startMonth <= 2) ? 'Winter' : (($startMonth >= 6 && $startMonth <= 8) ? 'Summer' : 'Spring');
                        if ($now < $start)           { $status = 'Scheduled'; $badgeClass = 'badge-scheduled'; }
                        elseif ($now >= $start && $now <= $end) { $status = 'Active';    $badgeClass = 'badge-active'; }
                        else                          { $status = 'Completed'; $badgeClass = 'badge-completed'; }
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['T_NAME']) ?></strong></td>
                            <td class="text-muted text-sm"><?= $season ?></td>
                            <td class="text-sm"><?= date('M d, Y', $start) ?></td>
                            <td class="text-sm"><?= date('M d, Y', $end) ?></td>
                            <td class="font-semibold"><?= number_format($t['PRIZE']) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $status ?></span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <button type="button" class="btn-action btn-action-edit" title="Edit Tournament"
                                            onclick='openEditModal(<?= json_encode([
                                                "id"        => $t["TOURNAMENTID"],
                                                "name"      => $t["T_NAME"],
                                                "sport"     => $t["SPORT_TYPE"],
                                                "season"    => $season,
                                                "startDate" => date("Y-m-d", $start),
                                                "endDate"   => date("Y-m-d", $end),
                                                "location"  => $t["LOCATION"] ?? "",
                                                "prize"     => (int)$t["PRIZE"],
                                                "maxTeams"  => (int)($t["MAX_TEAMS"] ?? 8)
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button type="button" class="btn-action btn-action-delete" title="Delete Tournament"
                                            onclick="confirmDelete(<?= (int)$t['TOURNAMENTID'] ?>, <?= htmlspecialchars(json_encode($t['T_NAME']), ENT_QUOTES, 'UTF-8') ?>)">
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
            <div class="table-footer">
                <span>Showing <?= count($tournaments) ?> of <?= $kpiTotal ?> tournaments</span>
                <div style="display:flex;gap:6px;">
                    <a href="#" class="page-btn">Previous</a>
                    <a href="#" class="page-btn">Next</a>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Create Tournament Modal -->
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:var(--radius-xl);padding:32px;width:540px;max-width:95vw;box-shadow:var(--shadow-lg);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2 style="font-size:1.1rem;">Create New Tournament</h2>
            <button id="closeModal" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.5rem;line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action_create" value="1">
            <div class="form-group">
                <label>Tournament Name *</label>
                <input type="text" name="t_name" class="form-control" placeholder="e.g. AIUB Autumn Cup 2025" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Sport Type *</label>
                    <select name="sport_type" class="form-control" required>
                        <option value="">Select Sport</option>
                        <option>Basketball</option><option>Football</option>
                        <option>Cricket</option><option>Volleyball</option>
                        <option>Badminton</option><option>Table Tennis</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Season</label>
                    <select name="season" class="form-control">
                        <option>Winter</option><option>Summer</option>
                        <option>Spring</option><option>Autumn</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date *</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Prize Money (BDT)</label>
                    <input type="number" name="prize_money" class="form-control" placeholder="e.g. 50000" min="0">
                </div>
                <div class="form-group">
                    <label>Max Teams</label>
                    <input type="number" name="max_teams" class="form-control" value="8" min="2">
                </div>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" class="form-control" placeholder="e.g. AIUB Indoor Stadium, Dhaka">
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
                <button type="button" id="cancelModal" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Tournament</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Tournament Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:var(--radius-xl);padding:32px;width:540px;max-width:95vw;box-shadow:var(--shadow-lg);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2 style="font-size:1.1rem;font-weight:700;">Edit Tournament</h2>
            <button id="closeEditModal" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.5rem;line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action_update" value="1">
            <input type="hidden" name="tournament_id" id="edit_t_id" value="">
            <div class="form-group">
                <label>Tournament Name *</label>
                <input type="text" name="t_name" id="edit_t_name" class="form-control" placeholder="e.g. AIUB Autumn Cup 2025" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Sport Type *</label>
                    <select name="sport_type" id="edit_sport_type" class="form-control" required>
                        <option value="">Select Sport</option>
                        <option value="Basketball">Basketball</option>
                        <option value="Football">Football</option>
                        <option value="Cricket">Cricket</option>
                        <option value="Volleyball">Volleyball</option>
                        <option value="Badminton">Badminton</option>
                        <option value="Table Tennis">Table Tennis</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Season</label>
                    <select name="season" id="edit_season" class="form-control">
                        <option value="Winter">Winter</option>
                        <option value="Summer">Summer</option>
                        <option value="Spring">Spring</option>
                        <option value="Autumn">Autumn</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date *</label>
                    <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Prize Money (BDT)</label>
                    <input type="number" name="prize_money" id="edit_prize_money" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label>Max Teams</label>
                    <input type="number" name="max_teams" id="edit_max_teams" class="form-control" min="2">
                </div>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" id="edit_location" class="form-control" placeholder="e.g. AIUB Indoor Stadium, Dhaka">
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px;">
                <button type="button" id="cancelEditModal" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Tournament Confirmation Modal -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:var(--radius-xl);padding:32px;width:460px;max-width:95vw;box-shadow:var(--shadow-lg);">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
            <div style="width:44px;height:44px;border-radius:50%;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <h2 style="font-size:1.15rem;font-weight:700;color:var(--text-main);margin:0;">Delete Tournament</h2>
                <p style="font-size:0.85rem;color:var(--text-muted);margin:3px 0 0;">This action cannot be undone.</p>
            </div>
        </div>
        <p style="font-size:0.92rem;color:var(--text-main);line-height:1.5;margin-bottom:16px;">
            Are you sure you want to delete <strong id="deleteTournamentName" style="color:#b91c1c;"></strong>?
            <span style="display:block;margin-top:8px;font-size:0.83rem;color:#b45309;background:#fef3c7;padding:8px 12px;border-radius:6px;line-height:1.4;">
                ⚠️ Note: Any matches and tickets tied to this tournament will also be permanently deleted.
            </span>
        </p>
        <form method="POST">
            <input type="hidden" name="action_delete" value="1">
            <input type="hidden" name="tournament_id" id="delete_t_id" value="">
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" id="cancelDeleteModal" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn" style="background:#dc2626;color:#fff;border:none;">Delete Tournament</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>js/main.js"></script>
<script>
// Create Modal
const createModal = document.getElementById('createModal');
const openBtn     = document.getElementById('openCreateModal');
const closeBtn    = document.getElementById('closeModal');
const cancelBtn   = document.getElementById('cancelModal');
if (openBtn)   openBtn.addEventListener('click', () => createModal.style.display = 'flex');
if (closeBtn)  closeBtn.addEventListener('click', () => createModal.style.display = 'none');
if (cancelBtn) cancelBtn.addEventListener('click', () => createModal.style.display = 'none');
createModal.addEventListener('click', e => { if (e.target === createModal) createModal.style.display = 'none'; });

// Edit Modal
const editModal       = document.getElementById('editModal');
const closeEditBtn    = document.getElementById('closeEditModal');
const cancelEditBtn   = document.getElementById('cancelEditModal');
if (closeEditBtn)  closeEditBtn.addEventListener('click', () => editModal.style.display = 'none');
if (cancelEditBtn) cancelEditBtn.addEventListener('click', () => editModal.style.display = 'none');
editModal.addEventListener('click', e => { if (e.target === editModal) editModal.style.display = 'none'; });

function openEditModal(data) {
    document.getElementById('edit_t_id').value = data.id || '';
    document.getElementById('edit_t_name').value = data.name || '';
    document.getElementById('edit_sport_type').value = data.sport || '';
    document.getElementById('edit_season').value = data.season || 'Spring';
    document.getElementById('edit_start_date').value = data.startDate || '';
    document.getElementById('edit_end_date').value = data.endDate || '';
    document.getElementById('edit_prize_money').value = data.prize || 0;
    document.getElementById('edit_max_teams').value = data.maxTeams || 8;
    document.getElementById('edit_location').value = data.location || '';
    editModal.style.display = 'flex';
}

// Delete Modal
const deleteModal     = document.getElementById('deleteModal');
const cancelDeleteBtn = document.getElementById('cancelDeleteModal');
if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', () => deleteModal.style.display = 'none');
deleteModal.addEventListener('click', e => { if (e.target === deleteModal) deleteModal.style.display = 'none'; });

function confirmDelete(id, name) {
    document.getElementById('delete_t_id').value = id;
    document.getElementById('deleteTournamentName').textContent = name;
    deleteModal.style.display = 'flex';
}

// Close on Escape key
window.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (createModal) createModal.style.display = 'none';
        if (editModal)   editModal.style.display = 'none';
        if (deleteModal) deleteModal.style.display = 'none';
    }
});

// Table search
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#tournamentsTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
</script>
</body>
</html>
