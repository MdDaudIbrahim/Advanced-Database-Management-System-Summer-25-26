<?php
// api/venue_check.php
// Real-time venue availability check for schedule_match.php

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$venueId = intval($_GET['venue_id'] ?? 0);
$date    = trim($_GET['date'] ?? '');
$time    = trim($_GET['time'] ?? '');

if (!$venueId || !$date || !$time) {
    echo json_encode(['conflict' => false]);
    exit();
}

$matchId = intval($_GET['match_id'] ?? 0);

$sql = "SELECT COUNT(*) AS CNT FROM MATCHES
        WHERE VENUEID = :vid
          AND MATCHDATE = TO_DATE(:md, 'YYYY-MM-DD')
          AND MATCHTIME = :mt
          AND MATCHSTATUS NOT IN ('Cancelled','Postponed')";
$params = ['vid' => $venueId, 'md' => $date, 'mt' => $time];

if ($matchId > 0) {
    $sql .= " AND MATCHID != :mid";
    $params['mid'] = $matchId;
}

$conn = getOracleConnection();
$rows = oracleQuery($conn, $sql, $params);

$cnt = $rows[0]['CNT'] ?? 0;
echo json_encode(['conflict' => $cnt > 0, 'count' => (int)$cnt]);
