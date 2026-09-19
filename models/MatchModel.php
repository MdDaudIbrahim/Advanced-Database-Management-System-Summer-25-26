<?php
// models/MatchModel.php
// Sports Tournament Management System (STMS)

require_once __DIR__ . '/../config/db.php';

class MatchModel {
    private $conn;

    public function __construct() {
        $this->conn = getOracleConnection();
    }

    public function getAllWithDetails() {
        $sql = "SELECT M.MATCHID, M.MATCHDATE, M.MATCHTIME, M.MATCHSTATUS, M.RESULT,
                       HT.TEAMNAME AS HOMETEAM, AT.TEAMNAME AS AWAYTEAM,
                       V.V_NAME AS VENUE, T.T_NAME AS TOURNAMENT
                FROM MATCHES M
                JOIN TEAM HT ON M.HOMETEAMID = HT.TEAMID
                JOIN TEAM AT ON M.AWAYTEAMID = AT.TEAMID
                JOIN VENUE V  ON M.VENUEID = V.VENUEID
                JOIN TOURNAMENT T ON M.TOURNAMENTID = T.TOURNAMENTID
                ORDER BY M.MATCHDATE DESC";
        return oracleQuery($this->conn, $sql);
    }

    public function getUpcoming($limit = 6) {
        $sql = "SELECT M.MATCHID, M.MATCHDATE, M.MATCHTIME,
                       HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM,
                       V.V_NAME AS VENUE, T.T_NAME AS TOURNAMENT
                FROM MATCHES M
                JOIN TEAM HT ON M.HOMETEAMID = HT.TEAMID
                JOIN TEAM AT ON M.AWAYTEAMID = AT.TEAMID
                JOIN VENUE V  ON M.VENUEID = V.VENUEID
                JOIN TOURNAMENT T ON M.TOURNAMENTID = T.TOURNAMENTID
                WHERE M.MATCHSTATUS = 'Scheduled' AND M.MATCHDATE >= TRUNC(SYSDATE)
                ORDER BY M.MATCHDATE ASC
                FETCH FIRST :lim ROWS ONLY";
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':lim', $limit);
        oci_execute($stmt);
        $rows = [];
        while ($r = oci_fetch_assoc($stmt)) $rows[] = $r;
        oci_free_statement($stmt);
        return $rows;
    }

    public function checkVenueConflict($venueId, $date, $time) {
        $sql = "SELECT COUNT(*) AS CNT FROM MATCHES 
                WHERE VENUEID = :vid 
                AND MATCHDATE = TO_DATE(:mdate, 'YYYY-MM-DD') 
                AND MATCHTIME = :mtime";
        $rows = oracleQuery($this->conn, $sql, ['vid' => $venueId, 'mdate' => $date, 'mtime' => $time]);
        return intval($rows[0]['CNT'] ?? 0) > 0;
    }

    public function schedule($tournamentId, $homeTeamId, $awayTeamId, $venueId, $matchDate, $matchTime) {
        if ($homeTeamId === $awayTeamId) return false;
        if ($this->checkVenueConflict($venueId, $matchDate, $matchTime)) return false;

        $sql = "INSERT INTO MATCHES VALUES (seq_matches.NEXTVAL, TO_DATE(:mdate, 'YYYY-MM-DD'), :mtime, 'Scheduled', NULL, :tid, :vid, :hid, :aid)";
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':mdate', $matchDate);
        oci_bind_by_name($stmt, ':mtime', $matchTime);
        oci_bind_by_name($stmt, ':tid',   $tournamentId);
        oci_bind_by_name($stmt, ':vid',   $venueId);
        oci_bind_by_name($stmt, ':hid',   $homeTeamId);
        oci_bind_by_name($stmt, ':aid',   $awayTeamId);
        return oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    }
}
