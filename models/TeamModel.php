<?php
// models/TeamModel.php
// Sports Tournament Management System (STMS)

require_once __DIR__ . '/../config/db.php';

class TeamModel {
    private $conn;

    public function __construct() {
        $this->conn = getOracleConnection();
    }

    public function getAll() {
        $sql = "SELECT TM.*, C.C_NAME AS COACH_NAME, T.T_NAME AS TOURNAMENT_NAME,
                       COUNT(P.PLAYERID) AS PLAYER_COUNT
                FROM TEAM TM
                LEFT JOIN COACH C ON TM.COACHID = C.COACHID
                LEFT JOIN TOURNAMENT T ON TM.TOURNAMENTID = T.TOURNAMENTID
                LEFT JOIN PLAYER P ON TM.TEAMID = P.TEAMID
                GROUP BY TM.TEAMID, TM.TEAMNAME, TM.CATEGORY, TM.HOMECITY, TM.REGDATE, TM.COACHID, TM.TOURNAMENTID, C.C_NAME, T.T_NAME
                ORDER BY TM.TEAMNAME ASC";
        return oracleQuery($this->conn, $sql);
    }

    public function getById($id) {
        $sql = "SELECT TM.*, C.C_NAME, C.EMAIL AS COACH_EMAIL, T.T_NAME
                FROM TEAM TM
                LEFT JOIN COACH C ON TM.COACHID = C.COACHID
                LEFT JOIN TOURNAMENT T ON TM.TOURNAMENTID = T.TOURNAMENTID
                WHERE TM.TEAMID = :id";
        $rows = oracleQuery($this->conn, $sql, ['id' => $id]);
        return $rows[0] ?? null;
    }

    public function getByCoachId($coachId) {
        $sql = "SELECT * FROM TEAM WHERE COACHID = :cid";
        $rows = oracleQuery($this->conn, $sql, ['cid' => $coachId]);
        return $rows[0] ?? null;
    }

    public function register($teamName, $category, $homeCity, $coachId, $tournamentId = null) {
        $sql = "INSERT INTO TEAM VALUES (seq_team.NEXTVAL, :tname, :cat, :city, SYSDATE, :cid, :tid)";
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':tname', $teamName);
        oci_bind_by_name($stmt, ':cat',   $category);
        oci_bind_by_name($stmt, ':city',  $homeCity);
        oci_bind_by_name($stmt, ':cid',   $coachId);
        oci_bind_by_name($stmt, ':tid',   $tournamentId);
        return oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    }
}
