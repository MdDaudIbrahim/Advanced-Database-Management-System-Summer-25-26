<?php
// views/admin/team_registration.php
// Admin Portal — Team Management & Directory

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); 
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';
$conn = getOracleConnection();

$successMsg = ''; 
$errorMsg   = '';

// ── Handle Delete Team ──────────────────────────────────
if (isset($_GET['delete_team'])) {
    $delId = intval($_GET['delete_team']);
    if ($delId > 0) {
        oracleExecute($conn, "DELETE FROM PLAYER WHERE TEAMID = :tid", ['tid' => $delId]);
        oracleExecute($conn, "DELETE FROM REGISTRATION WHERE TEAMID = :tid", ['tid' => $delId]);
        oracleExecute($conn, "DELETE FROM MATCHES WHERE HOMETEAMID = :tid OR AWAYTEAMID = :tid2", ['tid' => $delId, 'tid2' => $delId]);
        $ok = oracleExecute($conn, "DELETE FROM TEAM WHERE TEAMID = :tid", ['tid' => $delId]);
        if ($ok) {
            header("Location: " . BASE_URL . "views/admin/team_registration.php?msg=deleted");
            exit();
        } else {
            $errorMsg = "Could not delete team #{$delId}.";
        }
    }
}

// ── Handle Create New Team (POST) ───────────────────────
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'create_team') {
    $teamName  = trim($_POST['team_name'] ?? '');
    $sportType = trim($_POST['sport_type'] ?? 'Football');
    $category  = trim($_POST['category'] ?? 'Senior Division');
    $homeCity  = trim($_POST['home_city'] ?? 'Dhaka');
    $coachId   = intval($_POST['coach_id'] ?? 0);
    $tournId   = intval($_POST['tournament_id'] ?? 0) ?: null;

    if (empty($teamName) || $coachId <= 0) {
        $errorMsg = 'Please enter a team name and assign a head coach.';
    } else {
        // Check duplicate team name
        $dup = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TEAM WHERE LOWER(TEAMNAME) = LOWER(:tn)", ['tn' => $teamName]);
        if (($dup[0]['CNT'] ?? 0) > 0) {
            $errorMsg = "A team named '{$teamName}' is already registered.";
        } else {
            $ok = oracleExecute($conn, "
                INSERT INTO TEAM (TeamID, TeamName, Category, Sport_Type, HomeCity, CoachID, TournamentID)
                VALUES (seq_team.NEXTVAL, :tn, :cat, :sport, :city, :cid, :tournid)
            ", [
                'tn'      => $teamName,
                'cat'     => $category,
                'sport'   => $sportType,
                'city'    => $homeCity,
                'cid'     => $coachId,
                'tournid' => $tournId
            ]);

            if ($ok) {
                // If tournament selected, optionally add to REGISTRATION
                if ($tournId) {
                    $newTeam = oracleQuery($conn, "SELECT MAX(TEAMID) AS TID FROM TEAM WHERE LOWER(TEAMNAME) = LOWER(:tn)", ['tn' => $teamName]);
                    $newTid = $newTeam[0]['TID'] ?? null;
                    if ($newTid) {
                        oracleExecute($conn, "
                            INSERT INTO REGISTRATION (RegistrationID, RegDate, RegFee, TeamID, TournamentID)
                            VALUES (seq_registration.NEXTVAL, CURRENT_TIMESTAMP, 5000, :tid, :tournid)
                        ", ['tid' => $newTid, 'tournid' => $tournId]);
                    }
                }
                header("Location: " . BASE_URL . "views/admin/team_registration.php?msg=created");
                exit();
            } else {
                $errorMsg = "Could not register team. Please check inputs.";
            }
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $successMsg = 'New team registered and coach assigned successfully!';
    if ($_GET['msg'] === 'deleted') $successMsg = 'Team and associated records removed successfully.';
}

// ── Search & Filter ────────────────────────────────────
$filterSport = trim($_GET['sport'] ?? '');
$searchQ     = trim($_GET['q'] ?? '');

$sql = "
    SELECT T.TEAMID, T.TEAMNAME, T.SPORT_TYPE, T.CATEGORY, T.HOMECITY,
           C.C_NAME AS COACH_NAME, C.SPECIALIZATION,
           TR.T_NAME AS TOURNAMENT_NAME,
           COUNT(P.PLAYERID) AS PLAYER_COUNT
    FROM TEAM T
    LEFT JOIN COACH C ON T.COACHID = C.COACHID
    LEFT JOIN TOURNAMENT TR ON T.TOURNAMENTID = TR.TOURNAMENTID
    LEFT JOIN PLAYER P ON T.TEAMID = P.TEAMID
    WHERE 1=1
";
$params = [];
if (!empty($filterSport)) {
    $sql .= " AND LOWER(T.SPORT_TYPE) = LOWER(:fsport)";
    $params['fsport'] = $filterSport;
}
if (!empty($searchQ)) {
    $sql .= " AND (LOWER(T.TEAMNAME) LIKE LOWER(:q1) OR LOWER(C.C_NAME) LIKE LOWER(:q2) OR LOWER(T.HOMECITY) LIKE LOWER(:q3))";
    $qWild = '%' . $searchQ . '%';
    $params['q1'] = $qWild;
    $params['q2'] = $qWild;
    $params['q3'] = $qWild;
}
$sql .= " GROUP BY T.TEAMID, T.TEAMNAME, T.SPORT_TYPE, T.CATEGORY, T.HOMECITY, C.C_NAME, C.SPECIALIZATION, TR.T_NAME ORDER BY T.TEAMNAME";

$teams = oracleQuery($conn, $sql, $params);

// ── Summary KPI Counts ─────────────────────────────────
$allTeamsCount    = count(oracleQuery($conn, "SELECT TEAMID FROM TEAM"));
$footballCount    = count(oracleQuery($conn, "SELECT TEAMID FROM TEAM WHERE LOWER(SPORT_TYPE) = 'football'"));
$cricketCount     = count(oracleQuery($conn, "SELECT TEAMID FROM TEAM WHERE LOWER(SPORT_TYPE) = 'cricket'"));
$totalPlayerCount = count(oracleQuery($conn, "SELECT PLAYERID FROM PLAYER"));

// Dropdown data for modal/form
$coaches     = oracleQuery($conn, "SELECT COACHID, C_NAME, SPECIALIZATION FROM COACH ORDER BY C_NAME");
$tournaments = oracleQuery($conn, "SELECT TOURNAMENTID, T_NAME, SPORT_TYPE FROM TOURNAMENT ORDER BY STARTDATE DESC");

$currentPage = 'teams';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management (Teams Directory) — STMS Admin</title>
    <meta name="description" content="Register new teams, assign head coaches, and manage participating sports clubs.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/forms.css">
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>
    <main class="main-content">

        <!-- Topbar -->
        <div class="topbar">
            <span class="topbar-title">Admin Portal — STMS</span>
            <div class="topbar-right">
                <a href="<?= BASE_URL ?>views/admin/player_management.php" class="btn btn-ghost btn-sm">All Players Directory →</a>
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'AD', 0, 2)) ?></div>
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="breadcrumb" style="margin-bottom:8px;">
                <a href="<?= BASE_URL ?>views/admin/dashboard.php">Admin</a>
                <span style="margin:0 6px;color:var(--text-muted);">›</span>
                <span>Team Management</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-size:1.4rem;font-weight:800;">Team Management (Teams Directory)</h1>
                    <p class="page-subtitle">Register and oversee participating teams, assign head coaches, and monitor squad rosters.</p>
                </div>
                <button type="button" class="btn btn-primary" onclick="toggleAddTeamForm()">
                    + Add New Team
                </button>
            </div>
        </div>

        <?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg):   ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

        <!-- KPI Metrics Grid -->
        <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Registered Teams</span>
                    <div class="kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                </div>
                <div class="kpi-value"><?= $allTeamsCount ?></div>
                <span class="kpi-sub">Total clubs</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Football Clubs</span>
                    <div class="kpi-icon" style="color:#10b981;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/></svg></div>
                </div>
                <div class="kpi-value text-green"><?= $footballCount ?></div>
                <span class="kpi-sub positive">Active teams</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Cricket Clubs</span>
                    <div class="kpi-icon" style="color:#3b82f6;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/></svg></div>
                </div>
                <div class="kpi-value" style="color:#3b82f6;"><?= $cricketCount ?></div>
                <span class="kpi-sub">Active teams</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Total Roster Players</span>
                    <div class="kpi-icon" style="color:#8b5cf6;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                </div>
                <div class="kpi-value" style="color:#8b5cf6;"><?= $totalPlayerCount ?></div>
                <span class="kpi-sub">Registered athletes</span>
            </div>
        </div>

        <!-- Add New Team Form (Collapsible) -->
        <div class="card" id="addTeamCard" style="display:none;margin-bottom:24px;border:1px solid var(--border);">
            <div class="card-header" style="background:var(--bg-table-header);">
                <div>
                    <span class="card-title">🛡️ Register / Add New Team</span>
                    <div class="text-xs text-muted" style="margin-top:2px;">Define team specifications, assign an accredited coach, and establish home city.</div>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAddTeamForm()">✕ Close</button>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create_team">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Team Name *</label>
                            <input type="text" name="team_name" class="form-control" placeholder="e.g. Barisal Bulls, Comilla Victorians" required>
                        </div>
                        <div class="form-group">
                            <label>Sport Discipline *</label>
                            <select name="sport_type" class="form-control" required>
                                <option value="Football">Football</option>
                                <option value="Cricket">Cricket</option>
                                <option value="Basketball">Basketball</option>
                                <option value="Badminton">Badminton</option>
                                <option value="Volleyball">Volleyball</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Assigned Head Coach *</label>
                            <select name="coach_id" class="form-control" required>
                                <option value="">Select Certified Coach</option>
                                <?php foreach ($coaches as $c): ?>
                                <option value="<?= $c['COACHID'] ?>">
                                    <?= esc($c['C_NAME']) ?> — <?= esc($c['SPECIALIZATION'] ?? 'Sports Coach') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Home City / Campus *</label>
                            <input type="text" name="home_city" class="form-control" placeholder="e.g. Dhaka, Sylhet, Chittagong" value="Dhaka" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category / Division</label>
                            <input type="text" name="category" class="form-control" placeholder="e.g. Senior Division, Premier League" value="Senior Division">
                        </div>
                        <div class="form-group">
                            <label>Assign to Tournament (Optional)</label>
                            <select name="tournament_id" class="form-control">
                                <option value="">None (Independent Roster)</option>
                                <?php foreach ($tournaments as $tr): ?>
                                <option value="<?= $tr['TOURNAMENTID'] ?>">
                                    <?= esc($tr['T_NAME']) ?> (<?= esc($tr['SPORT_TYPE'] ?? '') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
                        <button type="button" class="btn btn-outline" onclick="toggleAddTeamForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Team & Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom:20px;padding:14px 20px;">
            <form method="GET" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:center;gap:10px;flex:1;max-width:420px;">
                    <input type="text" name="q" class="form-control" placeholder="Search team name, coach, or city..." value="<?= esc($searchQ) ?>" style="margin:0;">
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <label style="font-size:0.82rem;color:var(--text-muted);margin:0;">Sport:</label>
                    <select name="sport" class="form-control" style="width:auto;margin:0;" onchange="this.form.submit()">
                        <option value="">All Sports</option>
                        <option value="Football" <?= ($filterSport === 'Football') ? 'selected' : '' ?>>Football</option>
                        <option value="Cricket" <?= ($filterSport === 'Cricket') ? 'selected' : '' ?>>Cricket</option>
                        <option value="Basketball" <?= ($filterSport === 'Basketball') ? 'selected' : '' ?>>Basketball</option>
                        <option value="Badminton" <?= ($filterSport === 'Badminton') ? 'selected' : '' ?>>Badminton</option>
                    </select>
                    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
                    <?php if ($searchQ || $filterSport): ?>
                    <a href="<?= BASE_URL ?>views/admin/team_registration.php" class="btn btn-ghost btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Registered Teams Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Registered Teams Directory</span>
                <span class="text-muted text-sm"><?= count($teams) ?> teams listed</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Team Name</th>
                            <th>Sport</th>
                            <th>Home City</th>
                            <th>Head Coach</th>
                            <th>Squad Players</th>
                            <th>Enrolled Tournament</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($teams)): ?>
                        <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">No teams found matching your search.</td></tr>
                    <?php else: ?>
                    <?php foreach ($teams as $t): 
                        $initials = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $t['TEAMNAME']), 0, 2));
                    ?>
                    <tr>
                        <td class="font-bold text-muted"><?= $t['TEAMID'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;border-radius:var(--radius);background:var(--primary-light);color:var(--primary);font-weight:800;font-size:0.8rem;display:flex;align-items:center;justify-content:center;">
                                    <?= $initials ?>
                                </div>
                                <div>
                                    <strong style="color:var(--text-primary);font-size:0.95rem;"><?= esc($t['TEAMNAME']) ?></strong>
                                    <div class="text-xs text-muted"><?= esc($t['CATEGORY'] ?? 'General') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-scheduled" style="font-size:0.75rem;font-weight:600;"><?= esc($t['SPORT_TYPE']) ?></span>
                        </td>
                        <td class="text-sm font-semibold text-muted"><?= esc($t['HOMECITY'] ?? 'Dhaka') ?></td>
                        <td>
                            <div style="font-weight:700;color:var(--text-primary);"><?= esc($t['COACH_NAME'] ?? 'Unassigned') ?></div>
                            <div class="text-xs text-muted"><?= esc($t['SPECIALIZATION'] ?? 'Staff') ?></div>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>views/admin/player_management.php?team_id=<?= $t['TEAMID'] ?>"
                               class="badge badge-active" style="text-decoration:none;font-weight:700;" title="View Players in Squad">
                               <?= $t['PLAYER_COUNT'] ?> Players →
                            </a>
                        </td>
                        <td class="text-sm">
                            <?= !empty($t['TOURNAMENT_NAME']) ? '<strong>' . esc($t['TOURNAMENT_NAME']) . '</strong>' : '<span class="text-muted">None</span>' ?>
                        </td>
                        <td>
                            <div class="flex gap-4">
                                <a href="<?= BASE_URL ?>views/admin/player_management.php?team_id=<?= $t['TEAMID'] ?>"
                                   class="btn btn-ghost btn-sm text-green" style="font-weight:700;">
                                   Players
                                </a>
                                <a href="<?= BASE_URL ?>views/admin/team_registration.php?delete_team=<?= $t['TEAMID'] ?>"
                                   class="btn btn-ghost btn-sm" style="color:#ef4444;font-weight:700;"
                                   title="Delete Team" onclick="return confirm('Delete team \'<?= addslashes($t['TEAMNAME']) ?>\'? This will also remove associated matches and player assignments.');">
                                   ✕ Delete
                                </a>
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

<script src="<?= BASE_URL ?>js/main.js"></script>
<script>
function toggleAddTeamForm() {
    const card = document.getElementById('addTeamCard');
    if (card.style.display === 'none' || !card.style.display) {
        card.style.display = 'block';
        card.scrollIntoView({ behavior: 'smooth' });
    } else {
        card.style.display = 'none';
    }
}
</script>
</body>
</html>
