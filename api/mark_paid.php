<?php
// api/mark_paid.php — AJAX endpoint to mark a ticket as Paid
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$ticketId  = intval($_REQUEST['ticket_id'] ?? 0);
$payMethod = trim($_REQUEST['pay_method'] ?? 'Online');

if (!$ticketId) {
    echo json_encode(['success' => false, 'error' => 'Ticket ID is required']);
    exit();
}

$conn = getOracleConnection();
$sql  = "UPDATE TICKET SET PAYMENT_STATUS = 'Paid', PAY_METHOD = :pm WHERE TICKETID = :tid";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':pm',  $payMethod);
oci_bind_by_name($stmt, ':tid', $ticketId);
$ok   = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

if ($ok && oci_num_rows($stmt) > 0) {
    echo json_encode(['success' => true, 'ticket_id' => $ticketId, 'status' => 'Paid']);
} else {
    $e = oci_error($stmt);
    echo json_encode(['success' => false, 'error' => $e['message'] ?? 'Failed to update ticket']);
}
