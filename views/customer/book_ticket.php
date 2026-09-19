<?php
// views/customer/book_ticket.php
// Figma: "Book Ticket - Customer View.png"

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit();
}
require_once __DIR__ . '/../../config/db.php';
$conn = getOracleConnection();

$successMsg = ''; $errorMsg = '';
$myId = intval($_SESSION['user_id'] ?? 0);

// ── Handle Ticket Purchase ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_book'])) {
    $matchId    = intval($_POST['match_id'] ?? 0);
    $seatNos    = array_filter(array_map('intval', explode(',', $_POST['seat_nos'] ?? '')));
    $ticketType = trim($_POST['ticket_type'] ?? 'Standard');
    $price      = floatval($_POST['price_per_seat'] ?? 200);
    $payNow     = isset($_POST['pay_now']);

    if ($matchId && $myId && !empty($seatNos)) {
        $lastTicketId = 0;
        $booked = 0;
        foreach ($seatNos as $seatNo) {
            $check = oracleQuery($conn,
                "SELECT COUNT(*) AS CNT FROM TICKET WHERE MATCHID=:mid AND SEATNO=:sno",
                ['mid' => $matchId, 'sno' => $seatNo]
            );
            if (($check[0]['CNT'] ?? 0) > 0) {
                $errorMsg = "Seat #{$seatNo} is already taken. Please choose another.";
                break;
            }
            $payStatus = 'Pending';
            $sql  = "INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method)
                     VALUES (seq_ticket.NEXTVAL, :mid, :sid, :sno, :ttype, :price, :pstatus, 'Pending')";
            $ok = oracleExecute($conn, $sql, [
                'mid'     => $matchId,
                'sid'     => $myId,
                'sno'     => $seatNo,
                'ttype'   => $ticketType,
                'price'   => $price,
                'pstatus' => $payStatus
            ]);
            if ($ok) {
                $booked++;
                $tidRow = oracleQuery($conn, "SELECT MAX(TICKETID) AS LAST_ID FROM TICKET WHERE SPECTATORID=:sid AND MATCHID=:mid AND SEATNO=:sno", [
                    'sid' => $myId, 'mid' => $matchId, 'sno' => $seatNo
                ]);
                $lastTicketId = $tidRow[0]['LAST_ID'] ?? 0;
            } else {
                $errorMsg = 'Could not register ticket. Please try again.';
                break;
            }
        }

        if ($booked > 0 && !$errorMsg) {
            if ($payNow && $lastTicketId) {
                header("Location: " . BASE_URL . "views/customer/payment.php?ticket_id={$lastTicketId}&match_id={$matchId}&seat=" . reset($seatNos) . "&price={$price}");
                exit();
            } else {
                $firstSeat = reset($seatNos);
                $successMsg = "🎉 <strong>{$booked} seat(s) reserved successfully!</strong> Status: <em>Pending Payment</em>. "
                            . "<a href='" . BASE_URL . "views/customer/payment.php?ticket_id={$lastTicketId}&match_id={$matchId}&seat={$firstSeat}&price={$price}' class='btn btn-primary btn-sm' style='margin-left:10px;'>Pay Now (৳" . number_format($price * $booked) . ") →</a> "
                            . "<a href='" . BASE_URL . "views/customer/my_tickets.php' class='btn btn-outline btn-sm' style='margin-left:6px;'>View in My Bookings</a>";
            }
        }
    } else {
        $errorMsg = 'Please select a match and at least one seat.';
    }
}

// ── All upcoming matches ───────────────────────────
$allMatches = oracleQuery($conn, "
    SELECT M.MATCHID, HT.TEAMNAME AS HOME, AT.TEAMNAME AS AWAY,
           TO_CHAR(M.MATCHDATE,'DD Mon YYYY') AS MATCH_DATE,
           M.MATCHTIME, V.V_NAME AS VENUE, T.T_NAME AS TOURNAMENT
    FROM MATCHES M
    JOIN TEAM HT ON M.HOMETEAMID=HT.TEAMID
    JOIN TEAM AT ON M.AWAYTEAMID=AT.TEAMID
    JOIN VENUE V  ON M.VENUEID=V.VENUEID
    JOIN TOURNAMENT T ON M.TOURNAMENTID=T.TOURNAMENTID
    WHERE M.MATCHSTATUS IN ('Scheduled','Confirmed')
    ORDER BY M.MATCHDATE ASC
    FETCH FIRST 15 ROWS ONLY
");

// ── Selected Match ─────────────────────────────────
$selectedMatchId = intval($_GET['match_id'] ?? 0);
$tournamentId    = intval($_GET['tournament_id'] ?? 0);

if (!$selectedMatchId && $tournamentId) {
    $tMatch = oracleQuery($conn, "
        SELECT MATCHID FROM MATCHES
        WHERE TOURNAMENTID = :tid AND MATCHSTATUS IN ('Scheduled','Confirmed')
        ORDER BY MATCHDATE ASC
        FETCH FIRST 1 ROW ONLY
    ", ['tid' => $tournamentId]);
    if (!empty($tMatch)) {
        $selectedMatchId = intval($tMatch[0]['MATCHID']);
    }
}

if (!$selectedMatchId && !empty($allMatches)) {
    $selectedMatchId = intval($allMatches[0]['MATCHID']);
}

$matchInfo = null;
if ($selectedMatchId) {
    $res = oracleQuery($conn, "
        SELECT M.MATCHID, HT.TEAMNAME AS HOME, AT.TEAMNAME AS AWAY,
               V.V_NAME AS VENUE, V.CAPACITY,
               TO_CHAR(M.MATCHDATE,'Month DD, YYYY') AS MATCH_DATE,
               M.MATCHTIME, T.T_NAME AS TOURNAMENT
        FROM MATCHES M
        JOIN TEAM HT ON M.HOMETEAMID=HT.TEAMID
        JOIN TEAM AT ON M.AWAYTEAMID=AT.TEAMID
        JOIN VENUE V  ON M.VENUEID=V.VENUEID
        JOIN TOURNAMENT T ON M.TOURNAMENTID=T.TOURNAMENTID
        WHERE M.MATCHID = :mid
    ", ['mid' => $selectedMatchId]);
    $matchInfo = $res[0] ?? null;
}

// ── Get booked seats for selected match ────────────
$bookedSeats = [];
if ($selectedMatchId) {
    $bRes = oracleQuery($conn, "SELECT SEATNO FROM TICKET WHERE MATCHID=:mid", ['mid' => $selectedMatchId]);
    foreach ($bRes as $b) $bookedSeats[] = (int)$b['SEATNO'];
}
// Combine with standard Figma occupied seat mockup
$demoOccupied = [7, 17, 22, 23, 33, 41, 44, 55, 58, 60];
$allOccupiedSeats = array_unique(array_merge($bookedSeats, $demoOccupied));

// Gallery tab → price map
$galleryPrices = ['Main VIP' => 500, 'North Gallery' => 200, 'South Gallery' => 200, 'East Gallery' => 150, 'West Gallery' => 150];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Match Tickets — STMS</title>
    <meta name="description" content="Select your seats and book match tickets for upcoming university sports events.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --green: #1b5e20; --green-dark: #145016; --green-light: #e8f5e9;
            --border: #e2e8f0; --text: #1a202c; --muted: #718096;
            --bg: #f8fafc; --bg-card: #fff;
            --radius: 8px; --radius-lg: 12px;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
        a { text-decoration: none; color: inherit; }

        /* Navbar */
        .cust-nav {
            background: #fff; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 40px; height: 60px; gap: 24px;
        }
        .cust-nav-brand { font-weight: 800; font-size: 1.1rem; color: var(--green); }
        .nav-search {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 8px 14px; min-width: 280px;
        }
        .nav-search input { border: none; outline: none; background: transparent; font-family: inherit; font-size: 0.85rem; width: 100%; }
        .nav-links { display: flex; gap: 24px; margin-left: auto; align-items: center; }
        .nav-link { font-size: 0.9rem; font-weight: 500; color: var(--muted); padding: 4px 0; border-bottom: 2px solid transparent; }
        .nav-link.active, .nav-link:hover { color: var(--green); border-bottom-color: var(--green); }
        .nav-icons { display: flex; gap: 10px; margin-left: 16px; align-items: center; }
        .nav-icon {
            width: 32px; height: 32px; border: 1px solid var(--border);
            border-radius: var(--radius); background: #fff;
            display: flex; align-items: center; justify-content: center; color: var(--muted);
        }
        .nav-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--green); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.72rem; font-weight: 700;
        }

        /* Page Layout */
        .page-wrap { padding: 28px 40px; }
        .breadcrumb { font-size: 0.8rem; color: var(--muted); margin-bottom: 10px; }
        .breadcrumb a { color: var(--muted); }
        .breadcrumb span { color: var(--green); font-weight: 600; }
        .page-header-row {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 24px;
        }
        .page-title { font-size: 1.5rem; font-weight: 800; }
        .back-link {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.85rem; color: var(--muted);
            border: 1px solid var(--border);
            padding: 8px 16px; border-radius: var(--radius);
            background: #fff;
        }
        .back-link:hover { color: var(--green); }

        /* Main Layout */
        .booking-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: start;
        }

        /* Match Header */
        .match-header-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 22px 28px;
            background: #fff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .match-header-teams {
            display: flex;
            align-items: center;
            gap: 28px;
            flex: 1;
        }
        .match-team-c { text-align: center; }
        .team-logo {
            width: 52px; height: 52px;
            border: 1px solid var(--border);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            background: #f8fafc; margin: 0 auto 8px;
        }
        .team-label { font-size: 0.92rem; font-weight: 800; color: #0f172a; }
        .vs-c {
            color: #94a3b8; font-size: 0.95rem; font-weight: 700;
            flex-shrink: 0;
        }
        .match-header-sep { width: 1px; height: 55px; background: var(--border); }
        .match-header-meta { padding-left: 10px; }
        .match-meta-line {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.88rem; color: #334155; margin-bottom: 6px; font-weight: 500;
        }
        .match-switcher-c {
            margin-left: auto;
            text-align: right;
            padding-left: 20px;
            border-left: 1px solid var(--border);
        }
        .match-switcher-label {
            font-size: 0.68rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 5px;
        }
        .match-switcher-select {
            padding: 7px 12px; border: 1px solid var(--border);
            border-radius: 6px; background: #fff; font-size: 0.84rem;
            font-weight: 600; color: #0f172a; outline: none; cursor: pointer;
            font-family: inherit;
        }

        /* Seat Selection */
        .seat-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .seat-card-header {
            padding: 18px 24px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--border);
        }
        .seat-title { font-size: 1.05rem; font-weight: 800; color: #0f172a; }
        .seat-legend { display: flex; gap: 18px; align-items: center; font-size: 0.82rem; color: #64748b; }
        .legend-dot {
            width: 15px; height: 15px; border-radius: 3px;
            display: inline-block; margin-right: 6px; vertical-align: middle;
        }
        .ld-available { background: #fff; border: 1px solid #cbd5e1; }
        .ld-selected  { background: #166534; }
        .ld-occupied  { background: #e2e8f0; }

        /* Gallery Tabs */
        .gallery-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            gap: 4px;
            background: #fafafa;
        }
        .gallery-tab {
            padding: 14px 18px;
            font-size: 0.88rem; font-weight: 600; color: #64748b;
            cursor: pointer; border-bottom: 2px solid transparent;
            transition: all 0.2s; background: none; border-top: none;
            border-left: none; border-right: none; font-family: inherit;
        }
        .gallery-tab.active, .gallery-tab:hover {
            color: #166534; border-bottom-color: #166534; font-weight: 700;
        }

        /* Seat Grid */
        .seat-body { padding: 24px; }
        .seat-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        .seat {
            aspect-ratio: 1;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.72rem; font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
            background: #ffffff;
            color: #334155;
        }
        .seat:hover:not(.occupied) {
            border-color: #166534;
            background: #f0fdf4;
            color: #166534;
        }
        .seat.selected {
            background: #166534 !important;
            border-color: #166534 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(22, 101, 52, 0.35);
        }
        .seat.occupied {
            background: #e2e8f0 !important;
            border-color: #e2e8f0 !important;
            color: #94a3b8 !important;
            cursor: not-allowed;
        }
        /* Field view curved arch */
        .field-view {
            border-top: 5px solid #166534;
            border-radius: 180px 180px 0 0 / 26px 26px 0 0;
            background: #f8fafc;
            padding: 24px 20px 20px;
            text-align: center;
            margin-top: 10px;
        }
        .field-view-icon {
            width: 26px; height: 26px;
            margin: 0 auto 6px;
            color: #94a3b8;
        }
        .field-view-label {
            font-size: 0.76rem;
            font-weight: 800;
            color: #64748b;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        /* Booking Summary */
        .booking-summary {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .bs-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            font-size: 1.12rem; font-weight: 800; color: #0f172a;
        }
        .bs-body { padding: 24px; }
        .bs-row {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.88rem; margin-bottom: 14px;
            color: #64748b;
        }
        .bs-row-val { font-weight: 700; color: #0f172a; }
        .bs-divider { height: 1px; background: var(--border); margin: 18px 0; }
        .bs-total {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 16px 18px;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 22px;
        }
        .bs-total-label { font-weight: 700; color: #166534; font-size: 0.95rem; }
        .bs-total-val { font-size: 1.55rem; font-weight: 800; color: #166534; font-family: 'Inter', sans-serif; }
        .btn-pay-now {
            width: 100%; padding: 14px;
            background: #166534; color: #fff;
            font-size: 0.95rem; font-weight: 700;
            border: none; border-radius: 6px;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-bottom: 12px; transition: background 0.2s, transform 0.15s;
        }
        .btn-pay-now:hover { background: #145016; transform: translateY(-1px); }
        .btn-reserve {
            width: 100%; padding: 13px;
            background: #fff; color: #334155;
            font-size: 0.92rem; font-weight: 600;
            border: 1px solid #cbd5e1; border-radius: 6px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .btn-reserve:hover { border-color: #166534; color: #166534; }
    </style>
</head>
<body>

<!-- Unified Navbar -->
<?php $currentPage = 'book_ticket'; include __DIR__ . '/../../includes/customer_navbar.php'; ?>

<div class="page-wrap">
    <!-- Breadcrumb + Header -->
    <div class="breadcrumb">
        <a href="<?= BASE_URL ?>views/customer/book_ticket.php">Tickets</a> /
        <span>Match Booking</span>
    </div>
    <div class="page-header-row">
        <h1 class="page-title">Book Match Tickets</h1>
        <a href="<?= BASE_URL ?>views/customer/home.php" class="back-link">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Matches
        </a>
    </div>

    <?php if ($successMsg): ?><div style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:0.875rem;"><?= $successMsg ?></div><?php endif; ?>
    <?php if ($errorMsg):   ?><div style="background:#f8d7da;color:#721c24;border:1px solid #f5c2c7;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:0.875rem;"><?= $errorMsg ?></div><?php endif; ?>

    <?php if ($matchInfo): ?>
    <!-- Match header card -->
    <div class="match-header-card">
        <div class="match-header-teams">
            <div class="match-team-c">
                <div class="team-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div class="team-label"><?= htmlspecialchars($matchInfo['HOME']) ?></div>
            </div>
            <div class="vs-c">VS</div>
            <div class="match-team-c">
                <div class="team-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <div class="team-label"><?= htmlspecialchars($matchInfo['AWAY']) ?></div>
            </div>
        </div>
        <div class="match-header-sep"></div>
        <div class="match-header-meta">
            <div class="match-meta-line">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= htmlspecialchars($matchInfo['VENUE']) ?>
            </div>
            <div class="match-meta-line">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= htmlspecialchars(trim($matchInfo['MATCH_DATE'])) ?> &bull; <?= htmlspecialchars($matchInfo['MATCHTIME']) ?>
            </div>
        </div>
        <?php if (!empty($allMatches) && count($allMatches) > 1): ?>
        <div class="match-switcher-c">
            <label class="match-switcher-label">Switch Match</label>
            <select class="match-switcher-select" onchange="if(this.value) location.href='book_ticket.php?match_id='+this.value">
                <?php foreach ($allMatches as $m): ?>
                <option value="<?= $m['MATCHID'] ?>" <?= $m['MATCHID'] == $selectedMatchId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['HOME']) ?> vs <?= htmlspecialchars($m['AWAY']) ?> (<?= date('M d', strtotime($m['MATCH_DATE'])) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>

    <!-- Booking layout -->
    <div class="booking-layout">
        <!-- LEFT: Seat Selection -->
        <div>
            <div class="seat-card">
                <div class="seat-card-header">
                    <span class="seat-title">Seat Selection</span>
                    <div class="seat-legend">
                        <span><span class="legend-dot ld-available"></span>Available</span>
                        <span><span class="legend-dot ld-selected"></span>Selected</span>
                        <span><span class="legend-dot ld-occupied"></span>Occupied</span>
                    </div>
                </div>

                <!-- Gallery Tabs -->
                <div class="gallery-tabs" id="galleryTabs">
                    <?php $first = true; foreach ($galleryPrices as $tabName => $tabPrice): ?>
                    <button type="button" class="gallery-tab <?= $first ? 'active' : '' ?>"
                            data-gallery="<?= htmlspecialchars($tabName) ?>"
                            data-price="<?= $tabPrice ?>"
                            onclick="switchTab(this)">
                        <?= htmlspecialchars($tabName) ?>
                    </button>
                    <?php $first = false; endforeach; ?>
                </div>

                <!-- Seat Grid -->
                <div class="seat-body">
                    <div class="seat-grid" id="seatGrid">
                        <?php for ($s = 1; $s <= 60; $s++):
                            $isOccupied = in_array($s, $allOccupiedSeats);
                        ?>
                        <div class="seat <?= $isOccupied ? 'occupied' : '' ?>"
                             data-seat="<?= $s ?>"
                             <?= !$isOccupied ? 'onclick="toggleSeat(this)"' : '' ?>
                             title="Seat <?= $s ?><?= $isOccupied ? ' (Occupied)' : '' ?>">
                            <?= $s ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Field view curved indicator matching Figma -->
                    <div class="field-view">
                        <svg class="field-view-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="10"/>
                            <polygon points="12 8 16 11 14.5 16 9.5 16 8 11 12 8"/>
                            <line x1="12" y1="2" x2="12" y2="8"/>
                            <line x1="21.5" y1="9" x2="16" y2="11"/>
                            <line x1="18" y1="20" x2="14.5" y2="16"/>
                            <line x1="6" y1="20" x2="9.5" y2="16"/>
                            <line x1="2.5" y1="9" x2="8" y2="11"/>
                        </svg>
                        <div class="field-view-label">FIELD VIEW</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Booking Summary -->
        <div class="booking-summary">
            <div class="bs-header">Booking Summary</div>
            <div class="bs-body">
                <form method="POST">
                    <input type="hidden" name="action_book" value="1">
                    <input type="hidden" name="match_id" value="<?= $selectedMatchId ?>">
                    <input type="hidden" name="seat_nos" id="seatNosInput" value="">
                    <input type="hidden" name="ticket_type" id="ticketTypeInput" value="Main VIP">
                    <input type="hidden" name="price_per_seat" id="priceInput" value="500">

                    <div class="bs-row">
                        <span>Ticket Type</span>
                        <span class="bs-row-val" id="bsTicketType">VIP Premium</span>
                    </div>
                    <div class="bs-row">
                        <span>Quantity</span>
                        <span class="bs-row-val" id="bsQty">0 Seats</span>
                    </div>
                    <div class="bs-row">
                        <span>Price per seat</span>
                        <span class="bs-row-val" id="bsPriceEach">৳500.00</span>
                    </div>
                    <div class="bs-divider"></div>
                    <div class="bs-row">
                        <span>Subtotal</span>
                        <span class="bs-row-val" id="bsSubtotal">৳0.00</span>
                    </div>
                    <div class="bs-row">
                        <span>Service Charge (5%)</span>
                        <span class="bs-row-val" id="bsServiceCharge">৳0.00</span>
                    </div>
                    <div class="bs-row">
                        <span>VAT (7.5%)</span>
                        <span class="bs-row-val" id="bsVat">৳0.00</span>
                    </div>

                    <div class="bs-total">
                        <span class="bs-total-label">Total Amount</span>
                        <span class="bs-total-val" id="bsTotal">৳0.00</span>
                    </div>

                    <button type="submit" name="pay_now" class="btn-pay-now">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Pay Now
                    </button>
                    <button type="submit" class="btn-reserve">Reserve Ticket</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// ── State ──────────────────────────────────────────
let selectedSeats = [];
let pricePerSeat  = 500;
let galleryName   = 'Main VIP';

// ── Gallery tab switch ─────────────────────────────
function switchTab(btn) {
    document.querySelectorAll('.gallery-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    galleryName  = btn.dataset.gallery;
    pricePerSeat = parseInt(btn.dataset.price);
    document.getElementById('ticketTypeInput').value = galleryName;
    document.getElementById('priceInput').value      = pricePerSeat;
    document.getElementById('bsTicketType').textContent = galleryName;
    document.getElementById('bsPriceEach').textContent  = '৳' + pricePerSeat.toFixed(2);
    // clear selected seats when switching gallery
    selectedSeats = [];
    document.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));
    updateSummary();
}

// ── Toggle seat ────────────────────────────────────
function toggleSeat(el) {
    const seatNum = parseInt(el.dataset.seat);
    if (selectedSeats.includes(seatNum)) {
        selectedSeats = selectedSeats.filter(s => s !== seatNum);
        el.classList.remove('selected');
    } else {
        if (selectedSeats.length >= 6) {
            alert('You can select a maximum of 6 seats at once.');
            return;
        }
        selectedSeats.push(seatNum);
        el.classList.add('selected');
    }
    document.getElementById('seatNosInput').value = selectedSeats.join(',');
    updateSummary();
}

// ── Update booking summary ─────────────────────────
function updateSummary() {
    const qty      = selectedSeats.length;
    const subtotal = qty * pricePerSeat;
    const svc      = subtotal * 0.05;
    const vat      = subtotal * 0.075;
    const total    = subtotal + svc + vat;

    document.getElementById('bsQty').textContent          = qty + ' Seat' + (qty !== 1 ? 's' : '');
    document.getElementById('bsSubtotal').textContent      = '৳' + subtotal.toFixed(2);
    document.getElementById('bsServiceCharge').textContent = '৳' + svc.toFixed(2);
    document.getElementById('bsVat').textContent           = '৳' + vat.toFixed(2);
    document.getElementById('bsTotal').textContent         = '৳' + total.toFixed(2);
}

// Seat selection validation on form submit
const bookingForm = document.querySelector('.bs-body form');
if (bookingForm) {
    bookingForm.addEventListener('submit', function(e) {
        if (selectedSeats.length === 0) {
            e.preventDefault();
            alert('Please select at least one seat on the map before proceeding.');
        }
    });
}

// Init
updateSummary();
</script>
</body>
</html>
