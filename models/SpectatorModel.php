<?php
// models/SpectatorModel.php
// Sports Tournament Management System (STMS)

require_once __DIR__ . '/../config/db.php';

class SpectatorModel {
    private $conn;

    public function __construct() {
        $this->conn = getOracleConnection();
    }

    public function getByEmail($email) {
        $sql = "SELECT * FROM SPECTATOR WHERE EMAIL = :email";
        $rows = oracleQuery($this->conn, $sql, ['email' => $email]);
        return $rows[0] ?? null;
    }

    public function getById($id) {
        $sql = "SELECT * FROM SPECTATOR WHERE SPECTATORID = :id";
        $rows = oracleQuery($this->conn, $sql, ['id' => $id]);
        return $rows[0] ?? null;
    }

    public function register($name, $email, $phone) {
        $sql = "INSERT INTO SPECTATOR VALUES (seq_spectator.NEXTVAL, :name, :email, :phone)";
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':name',  $name);
        oci_bind_by_name($stmt, ':email', $email);
        oci_bind_by_name($stmt, ':phone', $phone);
        return oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    }
}
