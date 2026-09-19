<?php
// controllers/StaffController.php
// Sports Tournament Management System (STMS)

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit();
}

require_once __DIR__ . '/../models/TicketModel.php';

$action = $_GET['action'] ?? '';

if ($action === 'mark_paid' && isset($_GET['ticket_id'])) {
    $ticketId = intval($_GET['ticket_id']);
    $tModel   = new TicketModel();
    $ok       = $tModel->markAsPaid($ticketId, 'Cash');

    header('Location: ' . BASE_URL . 'views/staff/ticket_payments.php' . ($ok ? '?paid=1' : '?error=1'));
    exit();
}
