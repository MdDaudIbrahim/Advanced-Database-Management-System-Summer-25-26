<?php
// views/admin/dashboard.php
// Sports Tournament Management System (STMS) — Administrative Overview & User Management

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';
$conn = getOracleConnection();

$successMsg = '';
$errorMsg   = '';

// ── Ensure STAFF table exists and Password column on COACH ───────────
try {
    oracleExecute($conn, "
        CREATE TABLE IF NOT EXISTS STAFF (
            StaffID INTEGER PRIMARY KEY AUTOINCREMENT,
            S_Name TEXT NOT NULL,
            Email TEXT UNIQUE NOT NULL,
            Phone TEXT,
            Password TEXT DEFAULT 'staff123',
            Role TEXT DEFAULT 'Data Entry Staff',
            CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $staffCnt = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM STAFF")[0]['CNT'] ?? 0;
    if ($staffCnt == 0) {
        oracleExecute($conn, "
            INSERT INTO STAFF (StaffID, S_Name, Email, Phone, Password, Role)
            VALUES (501, 'Rahat Karim', 'rahat.k@gmail.com', '01712345678', 'staff123', 'Data Entry Staff')
        ");
    }
} catch (Exception $e) {}

// ── Handle Add Member (Coach or Staff) ────────────────────────────────
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $role     = trim($_POST['user_role'] ?? 'coach');
    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $spec     = trim($_POST['specialization'] ?? 'Football');

    if (empty($name) || empty($email) || empty($password)) {
        $errorMsg = 'Full Name, Email Address, and Password are required.';
    } else {
        if ($role === 'coach') {
            $dup = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM COACH WHERE LOWER(EMAIL) = LOWER(:e)", ['e' => $email]);
            if (($dup[0]['CNT'] ?? 0) > 0) {
                $errorMsg = "A coach with email {$email} already exists.";
            } else {
                $ok = oracleExecute($conn, "
                    INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary, Password)
                    VALUES (seq_coach.NEXTVAL, :n, :s, :e, 45000, :pwd)
                ", ['n' => $name, 's' => $spec, 'e' => $email, 'pwd' => $password]);

                if ($ok) {
                    $newC = oracleQuery($conn, "SELECT MAX(COACHID) AS CID FROM COACH WHERE LOWER(EMAIL) = LOWER(:e)", ['e' => $email]);
                    $cid  = $newC[0]['CID'] ?? null;
                    if ($cid && !empty($phone)) {
                        oracleExecute($conn, "INSERT INTO COACH_PHONE (CoachID, Phone) VALUES (:cid, :p)", ['cid' => $cid, 'p' => $phone]);
                    }
                    $successMsg = "Coach account created successfully! Login Email: <strong>" . esc($email) . "</strong> | Password: <strong>" . esc($password) . "</strong>";
                } else {
                    $errorMsg = 'Could not create coach account. Please try again.';
                }
            }
        } else {
            // Staff member
            $dup = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM STAFF WHERE LOWER(EMAIL) = LOWER(:e)", ['e' => $email]);
            if (($dup[0]['CNT'] ?? 0) > 0) {
                $errorMsg = "A staff member with email {$email} already exists.";
            } else {
                $ok = oracleExecute($conn, "
                    INSERT INTO STAFF (S_Name, Email, Phone, Password, Role)
                    VALUES (:n, :e, :p, :pwd, 'Data Entry Staff')
                ", ['n' => $name, 'e' => $email, 'p' => $phone, 'pwd' => $password]);

                if ($ok) {
                    $successMsg = "Staff account created successfully! Login Email: <strong>" . esc($email) . "</strong> | Password: <strong>" . esc($password) . "</strong>";
                } else {
                    $errorMsg = 'Could not create staff account. Please try again.';
                }
            }
        }
    }
}

// ── Handle Edit Member ────────────────────────────────────────────────
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $type     = trim($_POST['user_type'] ?? 'coach');
    $id       = intval($_POST['user_id'] ?? 0);
    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $spec     = trim($_POST['specialization'] ?? '');

    if ($id <= 0 || empty($name) || empty($email) || empty($password)) {
        $errorMsg = 'Please fill all required fields.';
    } else {
        if ($type === 'coach') {
            $ok = oracleExecute($conn, "
                UPDATE COACH 
                SET C_Name = :n, Email = :e, Password = :pwd, Specialization = :s 
                WHERE CoachID = :id
            ", ['n' => $name, 'e' => $email, 'pwd' => $password, 's' => $spec, 'id' => $id]);

            if ($ok) {
                oracleExecute($conn, "DELETE FROM COACH_PHONE WHERE CoachID = :id", ['id' => $id]);
                if (!empty($phone)) {
                    oracleExecute($conn, "INSERT INTO COACH_PHONE (CoachID, Phone) VALUES (:id, :p)", ['id' => $id, 'p' => $phone]);
                }
                $successMsg = "Coach <strong>" . esc($name) . "</strong> account credentials updated successfully!";
            } else {
                $errorMsg = 'Could not update coach account.';
            }
        } else {
            $ok = oracleExecute($conn, "
                UPDATE STAFF 
                SET S_Name = :n, Email = :e, Phone = :p, Password = :pwd 
                WHERE StaffID = :id
            ", ['n' => $name, 'e' => $email, 'p' => $phone, 'pwd' => $password, 'id' => $id]);

            if ($ok) {
                $successMsg = "Staff member <strong>" . esc($name) . "</strong> credentials updated successfully!";
            } else {
                $errorMsg = 'Could not update staff account.';
            }
        }
    }
}

// ── Handle Delete Member ──────────────────────────────────────────────
if (isset($_GET['delete_user']) && isset($_GET['type'])) {
    $delId   = intval($_GET['delete_user']);
    $delType = trim($_GET['type']);

    if ($delId > 0) {
        if ($delType === 'coach') {
            oracleExecute($conn, "UPDATE TEAM SET CoachID = NULL WHERE CoachID = :id", ['id' => $delId]);
            oracleExecute($conn, "DELETE FROM COACH_PHONE WHERE CoachID = :id", ['id' => $delId]);
            $del = oracleExecute($conn, "DELETE FROM COACH WHERE CoachID = :id", ['id' => $delId]);
            if ($del) {
                header("Location: " . BASE_URL . "views/admin/dashboard.php?msg=coach_deleted");
                exit();
            }
        } elseif ($delType === 'staff') {
            $del = oracleExecute($conn, "DELETE FROM STAFF WHERE StaffID = :id", ['id' => $delId]);
            if ($del) {
                header("Location: " . BASE_URL . "views/admin/dashboard.php?msg=staff_deleted");
                exit();
            }
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'coach_deleted') $successMsg = 'Coach account deleted successfully.';
    if ($_GET['msg'] === 'staff_deleted') $successMsg = 'Staff account deleted successfully.';
}

// ── KPI Queries ──────────────────────────────────
$activeTournaments = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TOURNAMENT WHERE SYSDATE BETWEEN STARTDATE AND ENDDATE")[0]['CNT'] ?? 0;
$scheduledMatches  = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM MATCHES WHERE MATCHSTATUS = 'Scheduled'")[0]['CNT'] ?? 0;
$registeredTeams   = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TEAM")[0]['CNT'] ?? 0;
$ticketsSold       = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TICKET")[0]['CNT'] ?? 0;

// ── Upcoming Matches (next 4) ────────────────────
$upcomingMatches = oracleQuery($conn, "
    SELECT m.MATCHID, m.MATCHDATE, m.MATCHTIME, m.MATCHSTATUS,
           t1.TEAMNAME AS HOME_TEAM, t2.TEAMNAME AS AWAY_TEAM,
           v.V_NAME AS VENUE
    FROM MATCHES m
    JOIN TEAM t1 ON m.HOMETEAMID = t1.TEAMID
    JOIN TEAM t2 ON m.AWAYTEAMID = t2.TEAMID
    JOIN VENUE v  ON m.VENUEID   = v.VENUEID
    WHERE m.MATCHSTATUS IN ('Scheduled','Confirmed')
    ORDER BY m.MATCHDATE ASC
    FETCH FIRST 4 ROWS ONLY
");

// ── Fetch Coaches & Staff for User Management ────
$coaches = oracleQuery($conn, "
    SELECT C.COACHID, C.C_NAME, C.SPECIALIZATION, C.EMAIL, C.SALARY, C.PASSWORD,
           (SELECT GROUP_CONCAT(PHONE, ', ') FROM COACH_PHONE CP WHERE CP.COACHID = C.COACHID) AS PHONES,
           (SELECT GROUP_CONCAT(TEAMNAME, ', ') FROM TEAM T WHERE T.COACHID = C.COACHID) AS TEAMS
    FROM COACH C
    ORDER BY C.COACHID ASC
");

$staffMembers = oracleQuery($conn, "
    SELECT STAFFID, S_NAME, EMAIL, PHONE, PASSWORD, ROLE, CREATEDAT
    FROM STAFF
    ORDER BY STAFFID ASC
");

$allUsers = [];
foreach ($coaches as $c) {
    $allUsers[] = [
        'type'           => 'coach',
        'id'             => $c['COACHID'],
        'member_code'    => '#CH-' . $c['COACHID'],
        'name'           => $c['C_NAME'],
        'role_title'     => 'Coach',
        'badge_class'    => 'badge-confirmed',
        'email'          => $c['EMAIL'],
        'phone'          => $c['PHONES'] ?: '—',
        'password'       => $c['PASSWORD'] ?: 'coach123',
        'info'           => $c['TEAMS'] ? $c['TEAMS'] : ($c['SPECIALIZATION'] ? $c['SPECIALIZATION'] . ' Coach' : 'Unassigned'),
        'specialization' => $c['SPECIALIZATION'] ?? 'Football'
    ];
}
foreach ($staffMembers as $s) {
    $allUsers[] = [
        'type'           => 'staff',
        'id'             => $s['STAFFID'],
        'member_code'    => '#ST-' . $s['STAFFID'],
        'name'           => $s['S_NAME'],
        'role_title'     => 'Data Entry Staff',
        'badge_class'    => 'badge-scheduled',
        'email'          => $s['EMAIL'],
        'phone'          => $s['PHONE'] ?: '—',
        'password'       => $s['PASSWORD'] ?: 'staff123',
        'info'           => 'Data Entry Portal Access',
        'specialization' => 'Data Entry'
    ];
}

$coachCount = count($coaches);
$staffCount = count($staffMembers);
$totalUsers = count($allUsers);

$currentPage = 'dashboard';
$pageTitle   = 'Administrative Overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Sports Tournament Management System</title>
    <meta name="description" content="Admin dashboard showing real-time tournament status, matches, teams, and user credentials management.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
    <style>
        .filter-tab { padding:6px 14px;border-radius:20px;font-size:0.8rem;font-weight:700;cursor:pointer;border:1px solid var(--border);background:transparent;color:var(--text-muted);transition:all .15s; }
        .filter-tab.active { background:var(--primary);color:#fff;border-color:var(--primary); }
        .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;align-items:center;justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:var(--bg-card);border-radius:var(--radius-xl);padding:28px;width:500px;max-width:95vw;box-shadow:var(--shadow-lg);animation:slideUp .2s ease;max-height:90vh;overflow-y:auto; }
        @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
        .modal-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;border-bottom:1px solid var(--border);padding-bottom:12px; }
        .modal-title { font-size:1.1rem;font-weight:800;color:var(--text-primary);margin:0; }
        .modal-close { background:transparent;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted); }
        .modal-close:hover { color:var(--text-primary); }
        .pwd-field-wrap { display:inline-flex;align-items:center;gap:6px;background:var(--bg-main);border:1px solid var(--border);border-radius:6px;padding:3px 8px;font-family:monospace;font-size:0.85rem; }
        .btn-action-icon { background:transparent;border:none;cursor:pointer;padding:4px 6px;border-radius:4px;transition:background .15s;font-size:0.85rem; }
        .btn-action-icon:hover { background:var(--border); }
    </style>
</head>
<body>
<div class="portal-layout">

    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <main class="main-content">

        <!-- Topbar -->
        <div class="topbar">
            <span class="topbar-title">Sports Tournament Management System</span>
            <div class="topbar-right">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" placeholder="Search tournaments...">
                </div>
                <a href="<?= BASE_URL ?>controllers/AuthController.php?action=logout" class="icon-btn" title="Logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-row">
                <div>
                    <h1 style="font-size:1.25rem;">Administrative Overview</h1>
                    <p class="page-subtitle">Real-time status of university athletic events.</p>
                </div>
                <a href="<?= BASE_URL ?>views/admin/tournaments.php" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    CREATE TOURNAMENT
                </a>
            </div>
        </div>

        <?php if ($successMsg): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <?= $successMsg ?>
        </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        <div class="alert alert-danger" style="margin-bottom:16px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?= $errorMsg ?>
        </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Active Tournaments</span>
                    <div class="kpi-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                </div>
                <div class="kpi-value"><?= $activeTournaments ?></div>
            </div>

            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Scheduled Matches</span>
                    <div class="kpi-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                </div>
                <div class="kpi-value"><?= $scheduledMatches ?></div>
            </div>

            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Registered Teams</span>
                    <div class="kpi-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                </div>
                <div class="kpi-value"><?= $registeredTeams ?></div>
            </div>

            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Tickets Sold</span>
                    <div class="kpi-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </div>
                </div>
                <div class="kpi-value"><?= $ticketsSold ?></div>
            </div>
        </div>

        <!-- 1. Upcoming Matches Section -->
        <div class="card" style="margin-top:24px;">
            <div class="card-header">
                <span class="card-title">Upcoming Matches</span>
                <div style="display:flex;gap:8px;">
                    <a href="<?= BASE_URL ?>views/admin/schedule_match.php" class="btn btn-outline btn-sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/></svg>
                        Filter
                    </a>
                    <button class="btn btn-outline btn-sm" onclick="window.print()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export
                    </button>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Match</th>
                            <th>Venue</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($upcomingMatches)): ?>
                        <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No upcoming matches found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($upcomingMatches as $m): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($m['HOME_TEAM']) ?> vs <?= htmlspecialchars($m['AWAY_TEAM']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($m['VENUE']) ?></td>
                            <td><?= date('M d, Y', strtotime($m['MATCHDATE'])) ?></td>
                            <td><?= htmlspecialchars($m['MATCHTIME']) ?></td>
                            <td>
                                <?php
                                $s = strtolower($m['MATCHSTATUS']);
                                $badgeClass = match($s) {
                                    'scheduled' => 'badge-scheduled',
                                    'confirmed' => 'badge-confirmed',
                                    'finished'  => 'badge-completed',
                                    default     => 'badge-pending',
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($m['MATCHSTATUS']) ?></span>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>views/admin/schedule_match.php?edit=<?= $m['MATCHID'] ?>"
                                   class="btn btn-ghost btn-sm text-green">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span>Showing <?= count($upcomingMatches) ?> of <?= $scheduledMatches ?> matches</span>
                <div style="display:flex;gap:6px;">
                    <a href="<?= BASE_URL ?>views/admin/schedule_match.php" class="page-btn">View All Matches →</a>
                </div>
            </div>
        </div>

        <!-- 2. System Users: Coach & Staff Member Credentials Management (CRUD) -->
        <div class="card" style="margin-top:24px;">
            <div class="card-header" style="flex-wrap:wrap;gap:12px;">
                <div>
                    <span class="card-title">User Accounts Management (Coach &amp; Staff)</span>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin:2px 0 0 0;">
                        Manage login credentials and system access. Coaches and staff accounts must be issued by the administrator.
                    </p>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="filter-tab active" id="tab-all-users" onclick="filterUsers('all', this)">All (<?= $totalUsers ?>)</button>
                        <button type="button" class="filter-tab" id="tab-coaches" onclick="filterUsers('coach', this)">Coaches (<?= $coachCount ?>)</button>
                        <button type="button" class="filter-tab" id="tab-staff" onclick="filterUsers('staff', this)">Staff (<?= $staffCount ?>)</button>
                    </div>
                    <div class="search-box" style="min-width:210px;padding:6px 10px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="userSearch" placeholder="Search accounts..." oninput="searchUserAccounts(this.value)">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openAddUserModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        + Add Member Account
                    </button>
                </div>
            </div>

            <div class="table-wrap">
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Login Email</th>
                            <th>Mobile Number</th>
                            <th>Password</th>
                            <th>Assigned Team / Details</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allUsers)): ?>
                        <tr><td colspan="8" class="text-center text-muted" style="padding:28px;">No coach or staff accounts registered.</td></tr>
                        <?php else: ?>
                        <?php foreach ($allUsers as $u): ?>
                        <tr data-type="<?= $u['type'] ?>" data-search="<?= strtolower(esc($u['member_code'] . ' ' . $u['name'] . ' ' . $u['email'] . ' ' . $u['phone'] . ' ' . $u['role_title'])) ?>">
                            <td>
                                <strong style="color:var(--primary);"><?= esc($u['member_code']) ?></strong>
                            </td>
                            <td>
                                <div style="font-weight:700;color:var(--text-primary);"><?= esc($u['name']) ?></div>
                            </td>
                            <td>
                                <span class="badge <?= $u['badge_class'] ?>">
                                    <?= esc($u['role_title']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight:600;color:var(--text-primary);"><?= esc($u['email']) ?></span>
                            </td>
                            <td>
                                <span class="text-sm font-semibold"><?= esc($u['phone']) ?></span>
                            </td>
                            <td>
                                <div class="pwd-field-wrap">
                                    <span class="user-pwd" data-pwd="<?= esc($u['password']) ?>">••••••••</span>
                                    <button type="button" class="btn-action-icon" title="Toggle password view" onclick="togglePasswordReveal(this)">
                                        👁️
                                    </button>
                                </div>
                            </td>
                            <td>
                                <span class="text-sm text-muted"><?= esc($u['info']) ?></span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <button type="button" class="btn btn-sm btn-ghost text-green" title="Edit Member Credentials"
                                            onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)">
                                        ✏️ Edit
                                    </button>
                                    <a href="<?= BASE_URL ?>views/admin/dashboard.php?delete_user=<?= $u['id'] ?>&type=<?= $u['type'] ?>"
                                       class="btn btn-sm btn-ghost" style="color:#ef4444;font-weight:700;"
                                       title="Delete Member Account"
                                       onclick="return confirm('Are you sure you want to delete <?= addslashes($u['role_title']) ?> \'<?= addslashes($u['name']) ?>\'? This will revoke system login access.');">
                                        🗑️
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
                <span>Total Active Accounts: <?= $totalUsers ?> (<?= $coachCount ?> Coaches, <?= $staffCount ?> Data Entry Staff)</span>
            </div>
        </div>

    </main>
</div>

<!-- ================= Modal 1: Add Member Account ================= -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">+ Add New Member (Coach / Staff)</h3>
            <button type="button" class="modal-close" onclick="closeAddUserModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Role <span style="color:#e53e3e;">*</span></label>
                <select name="user_role" id="addUserRole" class="form-control" style="margin-top:4px;" onchange="toggleAddRoleFields(this.value)" required>
                    <option value="coach">Coach</option>
                    <option value="staff">Data Entry Staff</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Full Name <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="full_name" class="form-control" placeholder="e.g. Asif Mahmud" required style="margin-top:4px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Email Address (Login Username) <span style="color:#e53e3e;">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="e.g. asif.m@gmail.com" required style="margin-top:4px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Mobile Number <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="phone" class="form-control" placeholder="e.g. 01711223344" required style="margin-top:4px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">System Password <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="password" class="form-control" placeholder="e.g. pass123" value="pass123" required style="margin-top:4px;">
                <small style="color:var(--text-muted);font-size:0.75rem;margin-top:4px;display:block;">
                    This password will be used by the member to sign in to the portal.
                </small>
            </div>

            <div id="addCoachFields" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Sport Specialization</label>
                <select name="specialization" class="form-control" style="margin-top:4px;">
                    <option value="Football">Football</option>
                    <option value="Cricket">Cricket</option>
                    <option value="Basketball">Basketball</option>
                    <option value="Badminton">Badminton</option>
                    <option value="Athletics">Athletics</option>
                </select>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">✓ Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= Modal 2: Edit Member Account ================= -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="editModalTitle">Edit Member Credentials</h3>
            <button type="button" class="modal-close" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_type" id="editUserType">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Role</label>
                <input type="text" id="editUserRoleDisplay" class="form-control" readonly style="background:var(--bg-main);margin-top:4px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Full Name <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="full_name" id="editUserName" class="form-control" required style="margin-top:4px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Login Email <span style="color:#e53e3e;">*</span></label>
                <input type="email" name="email" id="editUserEmail" class="form-control" required style="margin-top:4px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Mobile Number</label>
                <input type="text" name="phone" id="editUserPhone" class="form-control" style="margin-top:4px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Login Password <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="password" id="editUserPassword" class="form-control" required style="margin-top:4px;">
            </div>

            <div id="editCoachFields" style="margin-bottom:14px;display:none;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">Specialization</label>
                <input type="text" name="specialization" id="editUserSpec" class="form-control" style="margin-top:4px;">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">✓ Update Credentials</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>js/main.js"></script>
<script>
// ── Add User Modal ─────────────────────────────────
function openAddUserModal() {
    document.getElementById('addUserModal').classList.add('open');
}
function closeAddUserModal() {
    document.getElementById('addUserModal').classList.remove('open');
}
function toggleAddRoleFields(role) {
    const coachFields = document.getElementById('addCoachFields');
    if (coachFields) {
        coachFields.style.display = (role === 'coach') ? 'block' : 'none';
    }
}

// ── Edit User Modal ────────────────────────────────
function openEditUserModal(user) {
    document.getElementById('editModalTitle').textContent = `Edit Credentials (${user.member_code})`;
    document.getElementById('editUserType').value = user.type;
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserRoleDisplay').value = user.role_title;
    document.getElementById('editUserName').value = user.name;
    document.getElementById('editUserEmail').value = user.email;
    document.getElementById('editUserPhone').value = user.phone !== '—' ? user.phone : '';
    document.getElementById('editUserPassword').value = user.password;

    const editCoachFields = document.getElementById('editCoachFields');
    if (user.type === 'coach') {
        editCoachFields.style.display = 'block';
        document.getElementById('editUserSpec').value = user.specialization || 'Football';
    } else {
        editCoachFields.style.display = 'none';
    }

    document.getElementById('editUserModal').classList.add('open');
}
function closeEditUserModal() {
    document.getElementById('editUserModal').classList.remove('open');
}

// ── Toggle Password Visibility ─────────────────────
function togglePasswordReveal(btn) {
    const wrap = btn.closest('.pwd-field-wrap');
    const span = wrap.querySelector('.user-pwd');
    const realPwd = span.dataset.pwd;
    if (span.textContent === '••••••••') {
        span.textContent = realPwd;
        btn.textContent = '🙈';
    } else {
        span.textContent = '••••••••';
        btn.textContent = '👁️';
    }
}

// ── Close Modals on Overlay Click ──────────────────
document.getElementById('addUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddUserModal();
});
document.getElementById('editUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditUserModal();
});

// ── Filter and Search Users ────────────────────────
let currentRoleFilter = 'all';

function filterUsers(type, btn) {
    document.querySelectorAll('.card .filter-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    currentRoleFilter = type;
    applyUserFilters();
}

function searchUserAccounts(query) {
    applyUserFilters();
}

function applyUserFilters() {
    const q = (document.getElementById('userSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('#usersTable tbody tr').forEach(r => {
        const rType = r.dataset.type || '';
        const rSearch = r.dataset.search || '';

        const typeMatch = (currentRoleFilter === 'all') || (rType === currentRoleFilter);
        const searchMatch = !q || rSearch.includes(q);

        r.style.display = (typeMatch && searchMatch) ? '' : 'none';
    });
}
</script>
</body>
</html>
