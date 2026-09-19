<?php
// models/VenueModel.php
// Sports Tournament Management System (STMS)

require_once __DIR__ . '/../config/db.php';

class VenueModel {
    private $conn;

    public function __construct() {
        $this->conn = getOracleConnection();
    }

    public function getAll() {
        $sql = "SELECT * FROM VENUE ORDER BY V_NAME ASC";
        return oracleQuery($this->conn, $sql);
    }

    public function getById($id) {
        $sql = "SELECT * FROM VENUE WHERE VENUEID = :id";
        $rows = oracleQuery($this->conn, $sql, ['id' => $id]);
        return $rows[0] ?? null;
    }
}
