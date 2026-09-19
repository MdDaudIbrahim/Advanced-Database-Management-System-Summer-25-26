<?php
// models/TicketModel.php
// Sports Tournament Management System (STMS)

require_once __DIR__ . '/../config/db.php';

class TicketModel {
    private $conn;

    public function __construct() {
        $this->conn = getOracleConnection();
    }

    public function getAllWithDetails() {
        $sql = "SELECT TK.*, S.S_NAME, S.EMAIL AS SPECTATOR_EMAIL,
                       HT.TEAMNAME || ' vs ' || AT.TEAMNAME AS MATCH_LABEL,
                       TO_CHAR(M.MATCHDATE, 'DD Mon YYYY') AS MATCH_DATE,
                       M.MATCHTIME, V.V_NAME AS VENUE
                FROM TICKET TK
                JOIN SPECTATOR S ON TK.SPECTATORID = S.SPECTATORID
                JOIN MATCHES M   ON TK.MATCHID = M.MATCHID
                JOIN TEAM HT     ON M.HOMETEAMID = HT.TEAMID
                JOIN TEAM AT     ON M.AWAYTEAMID = AT.TEAMID
                JOIN VENUE V     ON M.VENUEID = V.VENUEID
                ORDER BY TK.TICKETID DESC";
        return oracleQuery($this->conn, $sql);
    }

    public function getBookedSeats($matchId) {
        $sql = "SELECT SEATNO FROM TICKET WHERE MATCHID = :mid";
        $rows = oracleQuery($this->conn, $sql, ['mid' => $matchId]);
        $seats = [];
        foreach ($rows as $r) {
            $seats[] = intval($r['SEATNO']);
        }
        return $seats;
    }

    public function isSeatAvailable($matchId, $seatNo) {
        $sql = "SELECT COUNT(*) AS CNT FROM TICKET WHERE MATCHID = :mid AND SEATNO = :sno";
        $rows = oracleQuery($this->conn, $sql, ['mid' => $matchId, 'sno' => $seatNo]);
        return intval($rows[0]['CNT'] ?? 0) === 0;
    }

    public function book($matchId, $spectatorId, $seatNo, $ticketType = 'Standard', $price = 200, $status = 'Pending') {
        if (!$this->isSeatAvailable($matchId, $seatNo)) return false;

        $sql = "INSERT INTO TICKET VALUES (seq_ticket.NEXTVAL, :mid, :sid, :sno, :ttype, :pr, :status, 'Online')";
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':mid',    $matchId);
        oci_bind_by_name($stmt, ':sid',    $spectatorId);
        oci_bind_by_name($stmt, ':sno',    $seatNo);
        oci_bind_by_name($stmt, ':ttype',  $ticketType);
        oci_bind_by_name($stmt, ':pr',     $price);
        oci_bind_by_name($stmt, ':status', $status);
        return oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    }

    public function markAsPaid($ticketId, $payMethod = 'SSLCommerz') {
        $sql = "UPDATE TICKET SET PAYMENT_STATUS = 'Paid', PAY_METHOD = :pm WHERE TICKETID = :tid";
        $stmt = oci_parse($this->conn, $sql);
        oci_bind_by_name($stmt, ':pm',  $payMethod);
        oci_bind_by_name($stmt, ':tid', $ticketId);
        return oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    }
}
