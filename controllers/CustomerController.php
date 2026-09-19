<?php
// controllers/CustomerController.php
// Sports Tournament Management System (STMS)

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');

require_once __DIR__ . '/../models/TicketModel.php';
require_once __DIR__ . '/../models/MatchModel.php';

$action = $_GET['action'] ?? '';

if ($action === 'book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit();
    }

    $matchId    = intval($_POST['match_id'] ?? 0);
    $seatNo     = intval($_POST['seat_no'] ?? 0);
    $ticketType = trim($_POST['ticket_type'] ?? 'Standard');
    $price      = floatval($_POST['price'] ?? 200);
    $spectatorId = $_SESSION['user_id'] ?? 0;

    $ticketModel = new TicketModel();
    if (!$ticketModel->isSeatAvailable($matchId, $seatNo)) {
        header('Location: ' . BASE_URL . 'views/customer/book_ticket.php?match_id=' . $matchId . '&error=SeatTaken');
        exit();
    }

    $ok = $ticketModel->book($matchId, $spectatorId, $seatNo, $ticketType, $price, 'Pending');
    if ($ok) {
        header('Location: ' . BASE_URL . 'views/customer/payment.php?match_id=' . $matchId . '&seat=' . $seatNo . '&price=' . $price);
        exit();
    } else {
        header('Location: ' . BASE_URL . 'views/customer/book_ticket.php?match_id=' . $matchId . '&error=Failed');
        exit();
    }
}
