<?php
// api/check_venue.php — AJAX endpoint for venue conflict check

header('Content-Type: application/json');
define('BASE_URL', '/ALL CODES/ADMS STMS/');
require_once __DIR__ . '/../config/db.php';

$venueId   = intval($_GET['venue_id'] ?? 0);
$matchDate = trim($_GET['date'] ?? '');
$matchTime = trim($_GET['time'] ?? '');

if (!$venueId || !$matchDate || !$matchTime) {
    echo json_encode(['conflict' => false, 'error' => 'Missing parameters']);
    exit();
}

$conn = getOracleConnection();

$sql  = "SELECT COUNT(*) AS CNT FROM MATCHES
         WHERE VENUEID = :vid
         AND MATCHDATE = TO_DATE(:md, 'YYYY-MM-DD')
         AND MATCHTIME = :mt";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':vid', $venueId);
oci_bind_by_name($stmt, ':md',  $matchDate);
oci_bind_by_name($stmt, ':mt',  $matchTime);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);
$cnt = intval($row['CNT']);

echo json_encode(['conflict' => $cnt > 0, 'count' => $cnt]);
