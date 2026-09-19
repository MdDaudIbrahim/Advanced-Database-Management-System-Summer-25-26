<?php
// controllers/CoachController.php
// Sports Tournament Management System (STMS)

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coach') {
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit();
}

require_once __DIR__ . '/../models/PlayerModel.php';

$action = $_GET['action'] ?? '';

if ($action === 'add_player' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['p_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $jerseyNo = intval($_POST['jersey_no'] ?? 0);
    $height   = floatval($_POST['height'] ?? 0);
    $weight   = floatval($_POST['weight'] ?? 0);
    $teamId   = intval($_SESSION['team_id'] ?? 0);
    $phone    = trim($_POST['phone'] ?? '');

    $pModel = new PlayerModel();
    $ok = $pModel->create($name, $position, $jerseyNo, $height, $weight, $teamId, $phone);

    header('Location: ' . BASE_URL . 'views/coach/my_players.php' . ($ok ? '?added=1' : '?error=1'));
    exit();
}
