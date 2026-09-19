<?php
// models/CoachModel.php
// Sports Tournament Management System (STMS)

require_once __DIR__ . '/../config/db.php';

class CoachModel {
    private $conn;

    public function __construct() {
        $this->conn = getOracleConnection();
    }

    public function getAllWithTeam() {
        $sql = "SELECT C.*, TM.TEAMNAME, TM.CATEGORY
                FROM COACH C
                LEFT JOIN TEAM TM ON C.COACHID = TM.COACHID
                ORDER BY C.C_NAME ASC";
        return oracleQuery($this->conn, $sql);
    }

    public function getById($id) {
        $sql = "SELECT C.*, TM.TEAMNAME, TM.TEAMID
                FROM COACH C
                LEFT JOIN TEAM TM ON C.COACHID = TM.COACHID
                WHERE C.COACHID = :id";
        $rows = oracleQuery($this->conn, $sql, ['id' => $id]);
        return $rows[0] ?? null;
    }

    public function getByEmail($email) {
        $sql = "SELECT * FROM COACH WHERE EMAIL = :email";
        $rows = oracleQuery($this->conn, $sql, ['email' => $email]);
        return $rows[0] ?? null;
    }
}
