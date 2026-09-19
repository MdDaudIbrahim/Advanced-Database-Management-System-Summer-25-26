<?php
// controllers/AdminController.php
// Sports Tournament Management System (STMS)

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit();
}

require_once __DIR__ . '/../models/TournamentModel.php';
require_once __DIR__ . '/../models/MatchModel.php';
require_once __DIR__ . '/../models/TeamModel.php';

$action = $_GET['action'] ?? '';

if ($action === 'create_tournament' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['t_name'] ?? '');
    $startDate  = trim($_POST['start_date'] ?? '');
    $endDate    = trim($_POST['end_date'] ?? '');
    $prizeMoney = floatval($_POST['prize_money'] ?? 0);
    $sportType  = trim($_POST['sport_type'] ?? 'Football');
    $location   = trim($_POST['location'] ?? 'Main Campus');
    $maxTeams   = intval($_POST['max_teams'] ?? 8);

    $tModel = new TournamentModel();
    $ok = $tModel->create($name, $startDate, $endDate, $prizeMoney, $sportType, $location, $maxTeams);

    header('Location: ' . BASE_URL . 'views/admin/tournaments.php' . ($ok ? '?success=1' : '?error=1'));
    exit();
}

if ($action === 'schedule_match' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tournamentId = intval($_POST['tournament_id'] ?? 0);
    $homeTeamId   = intval($_POST['home_team_id'] ?? 0);
    $awayTeamId   = intval($_POST['away_team_id'] ?? 0);
    $venueId      = intval($_POST['venue_id'] ?? 0);
    $matchDate    = trim($_POST['match_date'] ?? '');
    $matchTime    = trim($_POST['match_time'] ?? '');

    $mModel = new MatchModel();
    $ok = $mModel->schedule($tournamentId, $homeTeamId, $awayTeamId, $venueId, $matchDate, $matchTime);

    header('Location: ' . BASE_URL . 'views/admin/schedule_match.php' . ($ok ? '?success=1' : '?error=1'));
    exit();
}
