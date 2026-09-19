<?php
// =====================================================================
// helpers/helpers.php
// Common Helper Functions (Pattern followed from Web Tech Project)
// =====================================================================

function esc($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function is_blank($value) {
    return trim((string)($value ?? '')) === '';
}

function valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_date($date) {
    $parts = explode('-', $date);
    return count($parts) === 3
        && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

function valid_int($value, $min = 1) {
    return filter_var($value, FILTER_VALIDATE_INT) !== false && (int)$value >= $min;
}

function nice_date($date) {
    return ($date && $date !== '0000-00-00') ? date('d M Y', strtotime($date)) : '-';
}

function role_label($role) {
    $labels = [
        'admin'     => 'Administrator',
        'coach'     => 'Coach',
        'staff'     => 'Data Entry Staff',
        'employee'  => 'Employee',
        'customer'  => 'Customer / Spectator',
        'spectator' => 'Customer / Spectator',
    ];
    return $labels[$role] ?? ucfirst($role);
}

function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function render_flash() {
    $flashes = get_flash();
    if (empty($flashes)) return;
    foreach ($flashes as $f) {
        $alertClass = ($f['type'] === 'success') ? 'alert-success' : (($f['type'] === 'error') ? 'alert-danger' : 'alert-info');
        echo '<div class="alert ' . $alertClass . '" style="margin-bottom:16px;">' . esc($f['message']) . '</div>';
    }
}

/**
 * Ensures an enrolled team has a scheduled match fixture in MATCHES for that tournament.
 */
function ensureTournamentFixture($conn, $teamId, $tournId) {
    if (!$teamId || !$tournId) return false;

    // Check if match already exists for this team in this tournament
    $exists = oracleQuery($conn, "
        SELECT MATCHID FROM MATCHES 
        WHERE TOURNAMENTID = :tournid AND (HOMETEAMID = :tid OR AWAYTEAMID = :tid2)
    ", ['tournid' => $tournId, 'tid' => $teamId, 'tid2' => $teamId]);

    if (!empty($exists)) {
        return intval($exists[0]['MATCHID']);
    }

    // Get tournament info
    $tourn = oracleQuery($conn, "SELECT * FROM TOURNAMENT WHERE TOURNAMENTID = :id", ['id' => $tournId])[0] ?? null;
    if (!$tourn) return false;

    $sport = $tourn['SPORT_TYPE'] ?? 'General';

    // Find opponent
    // 1. Check other registered teams in this tournament
    $opp = oracleQuery($conn, "
        SELECT R.TEAMID 
        FROM REGISTRATION R
        WHERE R.TOURNAMENTID = :tournid AND R.TEAMID != :tid
    ", ['tournid' => $tournId, 'tid' => $teamId]);

    $oppId = 0;
    if (!empty($opp)) {
        $oppId = intval($opp[0]['TEAMID']);
    } else {
        // 2. Check team with same sport
        $sportOpp = oracleQuery($conn, "
            SELECT TEAMID FROM TEAM 
            WHERE TEAMID != :tid AND UPPER(SPORT_TYPE) = UPPER(:sport)
        ", ['tid' => $teamId, 'sport' => $sport]);
        
        if (!empty($sportOpp)) {
            $oppId = intval($sportOpp[0]['TEAMID']);
        } else {
            // 3. Fallback to any other team
            $anyOpp = oracleQuery($conn, "SELECT TEAMID FROM TEAM WHERE TEAMID != :tid", ['tid' => $teamId]);
            $oppId = intval($anyOpp[0]['TEAMID'] ?? 201);
        }
    }

    // Determine venue
    $venueId = 601;
    if (stripos($sport, 'football') !== false) $venueId = 602;
    elseif (stripos($sport, 'basket') !== false || stripos($sport, 'badminton') !== false) $venueId = 603;

    // Match Date
    $mDate = $tourn['STARTDATE'] ?? date('Y-m-d', strtotime('+3 days'));
    if (strtotime($mDate) < time() - 86400 * 30) {
        $mDate = date('Y-m-d', strtotime('+7 days'));
    }

    $ok = oracleExecute($conn, "
        INSERT INTO MATCHES (MatchID, TournamentID, HomeTeamID, AwayTeamID, VenueID, MatchDate, MatchTime, MatchStatus, MatchType, Match_Type)
        VALUES (seq_matches.NEXTVAL, :tournid, :homeid, :awayid, :vid, :mdate, '04:00 PM', 'Scheduled', 'Group Stage', 'Group Stage')
    ", [
        'tournid' => $tournId,
        'homeid'  => $teamId,
        'awayid'  => $oppId,
        'vid'     => $venueId,
        'mdate'   => $mDate
    ]);

    if ($ok) {
        $newM = oracleQuery($conn, "SELECT MAX(MATCHID) AS MID FROM MATCHES WHERE TOURNAMENTID = :tournid AND HOMETEAMID = :tid", ['tournid' => $tournId, 'tid' => $teamId]);
        return intval($newM[0]['MID'] ?? 1);
    }
    return false;
}

require_once __DIR__ . '/player_avatar.php';

