<?php
// models/TournamentModel.php
// Sports Tournament Management System (STMS)

require_once __DIR__ . '/../config/db.php';

class TournamentModel {
    private $conn;

    public function __construct() {
        $this->conn = getOracleConnection();
    }

    public function getAll() {
        $sql = "SELECT T.*, COUNT(DISTINCT M.MATCHID) AS MATCH_COUNT, COUNT(DISTINCT TM.TEAMID) AS TEAM_COUNT
                FROM TOURNAMENT T
                LEFT JOIN MATCHES M ON T.TOURNAMENTID = M.TOURNAMENTID
                LEFT JOIN TEAM TM   ON T.TOURNAMENTID = TM.TOURNAMENTID
                GROUP BY T.TOURNAMENTID, T.T_NAME, T.STARTDATE, T.ENDDATE, T.PRIZEMONEY, T.SPORT_TYPE, T.LOCATION, T.MAX_TEAMS
                ORDER BY T.STARTDATE DESC";
        return oracleQuery($this->conn, $sql);
    }

    public function getById($id) {
        $sql = "SELECT * FROM TOURNAMENT WHERE TOURNAMENTID = :id";
        $rows = oracleQuery($this->conn, $sql, ['id' => $id]);
        return $rows[0] ?? null;
    }

    public function create($name, $startDate, $endDate, $prizeMoney, $sportType = 'Football', $location = 'Main Campus', $maxTeams = 8) {
        $sql = "INSERT INTO TOURNAMENT VALUES (seq_tournament.NEXTVAL, :name, TO_DATE(:sdate, 'YYYY-MM-DD'), TO_DATE(:edate, 'YYYY-MM-DD'), :prize, :sport, :loc, :max_t)";
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':name',  $name);
        oci_bind_by_name($stmt, ':sdate', $startDate);
        oci_bind_by_name($stmt, ':edate', $endDate);
        oci_bind_by_name($stmt, ':prize', $prizeMoney);
        oci_bind_by_name($stmt, ':sport', $sportType);
        oci_bind_by_name($stmt, ':loc',   $location);
        oci_bind_by_name($stmt, ':max_t', $maxTeams);
        return oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    }

    public function delete($id) {
        $sql = "DELETE FROM TOURNAMENT WHERE TOURNAMENTID = :id";
        return oracleExecute($this->conn, $sql, ['id' => $id]);
    }
}
