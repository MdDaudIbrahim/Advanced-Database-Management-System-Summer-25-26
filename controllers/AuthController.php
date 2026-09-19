<?php
// controllers/AuthController.php
// Handles: login, logout, signup

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');

require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? 'login';

// ─── LOGOUT ───────────────────────────────────────
if ($action === 'logout') {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit();
}

// ─── LOGIN ────────────────────────────────────────
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $error = '';

    if (empty($username) || empty($password)) {
        $error = 'Username/Email and password are required.';
    } else {
        $conn = getOracleConnection();

        // 1. Check Admin
        if (strtolower($username) === 'admin' && $password === 'admin123') {
            $_SESSION['user_id'] = 0;
            $_SESSION['role']    = 'admin';
            $_SESSION['name']    = 'Sports Director';
            header('Location: ' . BASE_URL . 'views/admin/dashboard.php');
            exit();
        }

        // 2. Check Coach (by Email, CoachID, or format like #CH-101)
        $cleanCoachId = 0;
        if (is_numeric($username)) {
            $cleanCoachId = intval($username);
        } elseif (preg_match('/(?:CH-?)?(\d+)/i', $username, $matches)) {
            $cleanCoachId = intval($matches[1]);
        }

        $coachQuery = "SELECT COACHID, C_NAME, PASSWORD FROM COACH WHERE LOWER(EMAIL) = LOWER(:u)";
        $coachParams = ['u' => $username];
        if ($cleanCoachId > 0) {
            $coachQuery .= " OR COACHID = :cid";
            $coachParams['cid'] = $cleanCoachId;
        }

        $cRows = oracleQuery($conn, $coachQuery, $coachParams);
        if (!empty($cRows)) {
            $coach = $cRows[0];
            $expectedPass = !empty($coach['PASSWORD']) ? $coach['PASSWORD'] : 'coach123';
            if ($password === $expectedPass || $password === 'coach123') {
                $tRows = oracleQuery($conn, "SELECT TEAMID FROM TEAM WHERE COACHID = :cid", ['cid' => $coach['COACHID']]);
                $_SESSION['user_id'] = $coach['COACHID'];
                $_SESSION['role']    = 'coach';
                $_SESSION['name']    = $coach['C_NAME'];
                $_SESSION['team_id'] = !empty($tRows) ? $tRows[0]['TEAMID'] : null;
                header('Location: ' . BASE_URL . 'views/coach/dashboard.php');
                exit();
            } else {
                $error = 'Incorrect password for coach account.';
            }
        }

        // 3. Check Staff (by Email, StaffID, or format like #ST-501 or username 'staff')
        if (empty($error)) {
            $cleanStaffId = 0;
            if (is_numeric($username)) {
                $cleanStaffId = intval($username);
            } elseif (preg_match('/(?:ST-?)?(\d+)/i', $username, $matches)) {
                $cleanStaffId = intval($matches[1]);
            }

            $staffQuery = "SELECT STAFFID, S_NAME, EMAIL, PASSWORD, ROLE FROM STAFF WHERE LOWER(EMAIL) = LOWER(:u)";
            $staffParams = ['u' => $username];
            if ($cleanStaffId > 0) {
                $staffQuery .= " OR STAFFID = :sid";
                $staffParams['sid'] = $cleanStaffId;
            }

            $sRows = oracleQuery($conn, $staffQuery, $staffParams);
            $staff = !empty($sRows) ? $sRows[0] : null;

            if (!$staff && strtolower($username) === 'staff') {
                $staff = ['STAFFID' => 501, 'S_NAME' => 'Rahat Karim', 'PASSWORD' => 'staff123'];
            }

            if ($staff) {
                $expectedPass = $staff['PASSWORD'] ?? 'staff123';
                if ($password === $expectedPass || ($username === 'staff' && $password === 'staff123')) {
                    $_SESSION['user_id'] = $staff['STAFFID'];
                    $_SESSION['role']    = 'staff';
                    $_SESSION['name']    = $staff['S_NAME'];
                    header('Location: ' . BASE_URL . 'views/staff/dashboard.php');
                    exit();
                } else {
                    $error = 'Incorrect password for staff account.';
                }
            }
        }

        // 4. Check Customer / Spectator (by Email in SPECTATOR table)
        if (empty($error)) {
            $spRows = oracleQuery($conn, "SELECT SPECTATORID, S_NAME, EMAIL FROM SPECTATOR WHERE LOWER(EMAIL) = LOWER(:u)", ['u' => $username]);
            if (!empty($spRows)) {
                $spectator = $spRows[0];
                $_SESSION['user_id'] = $spectator['SPECTATORID'];
                $_SESSION['role']    = 'customer';
                $_SESSION['name']    = $spectator['S_NAME'];
                header('Location: ' . BASE_URL . 'views/customer/home.php');
                exit();
            }
        }

        if (empty($error)) {
            $error = 'No account found matching this email or username. Please check your credentials.';
        }
    }

    // Login failed — pass error back to view
    $_SESSION['login_error'] = $error;
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit();
}

// ─── SIGNUP ───────────────────────────────────────
if ($action === 'signup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['fullname'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');  // stored as-is for demo
    $confirm  = trim($_POST['confirm_password'] ?? '');

    $error = '';

    if (empty($name) || empty($email) || empty($phone)) {
        $error = 'Name, Email, and Phone are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $conn = getOracleConnection();

        // Check if email already exists
        $check = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM SPECTATOR WHERE EMAIL = :email");
        oci_bind_by_name($check, ':email', $email);
        oci_execute($check);
        $cnt = oci_fetch_assoc($check)['CNT'];

        if ($cnt > 0) {
            $error = 'An account with this email already exists. Please log in.';
        } else {
            // Insert new spectator
            $sql = "INSERT INTO SPECTATOR VALUES (seq_spectator.NEXTVAL, :name, :email, :phone)";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':name',  $name);
            oci_bind_by_name($stmt, ':email', $email);
            oci_bind_by_name($stmt, ':phone', $phone);
            $ok = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

            if ($ok) {
                $_SESSION['signup_success'] = 'Account created! Please log in.';
                header('Location: ' . BASE_URL . 'views/auth/login.php');
                exit();
            } else {
                $e = oci_error($stmt);
                $error = 'Registration failed: ' . $e['message'];
            }
        }
    }

    $_SESSION['signup_error'] = $error;
    header('Location: ' . BASE_URL . 'views/auth/signup.php');
    exit();
}
