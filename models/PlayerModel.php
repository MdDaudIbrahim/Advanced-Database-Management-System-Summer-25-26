<?php
// models/PlayerModel.php
// Sports Tournament Management System (STMS)

require_once __DIR__ . '/../config/db.php';

class PlayerModel {
    private $conn;

    public function __construct() {
        $this->conn = getOracleConnection();
    }

    public function getAllWithTeam() {
        $sql = "SELECT P.*, TM.TEAMNAME, TM.CATEGORY
                FROM PLAYER P
                JOIN TEAM TM ON P.TEAMID = TM.TEAMID
                ORDER BY TM.TEAMNAME, P.JERSEYNO";
        return oracleQuery($this->conn, $sql);
    }

    public function getByTeamId($teamId) {
        $sql = "SELECT * FROM PLAYER WHERE TEAMID = :tid ORDER BY JERSEYNO ASC";
        return oracleQuery($this->conn, $sql, ['tid' => $teamId]);
    }

    public function create($name, $position, $jerseyNo, $height, $weight, $teamId, $phone = '') {
        $sql = "INSERT INTO PLAYER VALUES (seq_player.NEXTVAL, :pname, :pos, :jno, :h, :w, :tid)";
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':pname', $name);
        oci_bind_by_name($stmt, ':pos',   $position);
        oci_bind_by_name($stmt, ':jno',   $jerseyNo);
        oci_bind_by_name($stmt, ':h',     $height);
        oci_bind_by_name($stmt, ':w',     $weight);
        oci_bind_by_name($stmt, ':tid',   $teamId);
        $ok = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

        if ($ok && !empty($phone)) {
            $pSql = "INSERT INTO PLAYER_PHONE VALUES (seq_player.CURRVAL, :phone)";
            $pStmt = oci_parse($this->conn, $pSql);
            oci_bind_by_name($pStmt, ':phone', $phone);
            oci_execute($pStmt, OCI_COMMIT_ON_SUCCESS);
        }
        return $ok;
    }

    public function delete($id) {
        $sql = "DELETE FROM PLAYER WHERE PLAYERID = :id";
        return oracleExecute($this->conn, $sql, ['id' => $id]);
    }
}
