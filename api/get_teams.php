<?php
// api/get_teams.php — AJAX endpoint to fetch teams for match scheduling
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$tournamentId = intval($_GET['tournament_id'] ?? 0);
$conn = getOracleConnection();

if ($tournamentId > 0) {
    $sql = "
        SELECT TM.TEAMID, TM.TEAMNAME, TM.CATEGORY, TM.HOMECITY
        FROM TEAM TM
        JOIN REGISTRATION R ON TM.TEAMID = R.TEAMID
        WHERE R.TOURNAMENTID = :tid
        ORDER BY TM.TEAMNAME ASC
    ";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':tid', $tournamentId);
} else {
    $sql = "SELECT TEAMID, TEAMNAME, CATEGORY, HOMECITY FROM TEAM ORDER BY TEAMNAME ASC";
    $stmt = oci_parse($conn, $sql);
}

oci_execute($stmt);
$teams = [];
while ($row = oci_fetch_assoc($stmt)) {
    $teams[] = $row;
}
oci_free_statement($stmt);

echo json_encode(['success' => true, 'teams' => $teams]);
