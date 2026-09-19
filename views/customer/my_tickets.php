<?php
// views/customer/my_tickets.php
// Customer Portal — My Bookings & Payment Status

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn = getOracleConnection();

$myId = intval($_SESSION['user_id'] ?? 0);
$myName = $_SESSION['name'] ?? 'Spectator';

// Fetch all tickets for this spectator
$tickets = oracleQuery($conn, "
    SELECT TK.TICKETID, TK.SEATNO, TK.TICKET_TYPE, TK.PRICE, TK.PAYMENT_STATUS, TK.PAY_METHOD,
           M.MATCHID, M.MATCHDATE, M.MATCHTIME,
           HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM,
           V.V_NAME AS VENUE, V.LOCATION,
           T.T_NAME AS TOURNAMENT, T.SPORT_TYPE
    FROM TICKET TK
    JOIN MATCHES M   ON TK.MATCHID = M.MATCHID
    JOIN TEAM HT     ON M.HOMETEAMID = HT.TEAMID
    JOIN TEAM AT     ON M.AWAYTEAMID = AT.TEAMID
    JOIN VENUE V     ON M.VENUEID = V.VENUEID
    JOIN TOURNAMENT T ON M.TOURNAMENTID = T.TOURNAMENTID
    WHERE TK.SPECTATORID = :sid
    ORDER BY TK.TICKETID DESC
", ['sid' => $myId]);

// Summary statistics
$totalTickets = count($tickets);
$paidTickets = 0;
$pendingTickets = 0;
$totalSpent = 0;

foreach ($tickets as $t) {
    if (strcasecmp($t['PAYMENT_STATUS'], 'Paid') === 0) {
        $paidTickets++;
        $totalSpent += floatval($t['PRICE']);
    } else {
        $pendingTickets++;
    }
}

$currentPage = 'my_tickets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings &amp; Tickets — STMS</title>
    <meta name="description" content="View your booked match tickets, payment status, and receipts on STMS Customer Portal.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; margin: 0; }
        .page-container { max-width: 1200px; margin: 30px auto; padding: 0 24px; }
        .page-header-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
        .page-title { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 6px 0; }
        .page-desc { font-size: 0.88rem; color: #64748b; margin: 0; }
        .btn-book-more {
            background: #1b5e20; color: #fff; padding: 10px 18px; border-radius: 8px;
            font-size: 0.85rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
            transition: background 0.2s;
        }
        .btn-book-more:hover { background: #145016; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .stat-label { font-size: 0.78rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .stat-value { font-size: 1.7rem; font-weight: 800; color: #0f172a; margin-top: 4px; }
        .stat-sub { font-size: 0.75rem; color: #166534; font-weight: 600; margin-top: 4px; }

        /* Table Card */
        .tickets-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
        .tickets-header { padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .tickets-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; }

        .ticket-table { width: 100%; border-collapse: collapse; text-align: left; }
        .ticket-table th { background: #f8fafc; padding: 12px 18px; font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .ticket-table td { padding: 16px 18px; font-size: 0.86rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .ticket-table tr:hover { background: #fbfcfe; }

        .badge-status { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .badge-status-paid { background: #dcfce7; color: #166534; }
        .badge-status-pending { background: #fef3c7; color: #92400e; }

        .action-pay {
            background: #1b5e20; color: #fff; padding: 6px 14px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px; transition: background 0.2s;
        }
        .action-pay:hover { background: #145016; }

        .action-print {
            background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;
        }
        .action-print:hover { background: #e2e8f0; color: #0f172a; }

        .empty-state { padding: 48px; text-align: center; color: #64748b; }
        .empty-icon { width: 48px; height: 48px; stroke: #94a3b8; margin: 0 auto 12px auto; display: block; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../../includes/customer_navbar.php'; ?>

    <div class="page-container">
        <div class="page-header-row">
            <div>
                <h1 class="page-title">My Bookings &amp; Payment Ledger</h1>
                <p class="page-desc">Track all your purchased seats, match passes, and payment confirmation statuses.</p>
            </div>
            <a href="<?= BASE_URL ?>views/customer/book_ticket.php" class="btn-book-more">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Book New Ticket
            </a>
        </div>

        <!-- KPI Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-value"><?= $totalTickets ?></div>
                <div class="stat-sub">All-time passes</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Confirmed / Paid</div>
                <div class="stat-value" style="color:#166534;"><?= $paidTickets ?></div>
                <div class="stat-sub">Ready for entry</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending Approval / Payment</div>
                <div class="stat-value" style="color:#b45309;"><?= $pendingTickets ?></div>
                <div class="stat-sub" style="color:#b45309;"><?= $pendingTickets > 0 ? 'Action required' : 'All clear' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Amount Paid</div>
                <div class="stat-value">৳<?= number_format($totalSpent) ?></div>
                <div class="stat-sub">BDT settled</div>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="tickets-card">
            <div class="tickets-header">
                <div class="tickets-title">All Purchased &amp; Reserved Tickets</div>
                <span style="font-size:0.82rem;color:#64748b;"><?= count($tickets) ?> records found</span>
            </div>

            <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke-width="1.5">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
                <h3 style="margin:0 0 6px 0;font-size:1.1rem;color:#0f172a;">No ticket bookings yet</h3>
                <p style="margin:0 0 16px 0;font-size:0.88rem;">Explore ongoing university tournaments and book your front-row seat today!</p>
                <a href="<?= BASE_URL ?>views/customer/book_ticket.php" class="btn-book-more">Browse Matches</a>
            </div>
            <?php else: ?>
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Tournament &amp; Match</th>
                        <th>Schedule</th>
                        <th>Venue</th>
                        <th>Seat &amp; Tier</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): 
                        $isPaid = (strcasecmp($t['PAYMENT_STATUS'], 'Paid') === 0);
                    ?>
                    <tr>
                        <td>
                            <strong style="color:#0f172a;">#TK-<?= htmlspecialchars($t['TICKETID']) ?></strong>
                            <div style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($t['PAY_METHOD'] ?: 'Card') ?></div>
                        </td>
                        <td>
                            <div style="font-weight:700;color:#0f172a;"><?= htmlspecialchars($t['HOME_TEAM']) ?> vs <?= htmlspecialchars($t['AWAY_TEAM']) ?></div>
                            <div style="font-size:0.75rem;color:#1b5e20;"><?= htmlspecialchars($t['TOURNAMENT']) ?> (<?= htmlspecialchars($t['SPORT_TYPE']) ?>)</div>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= date('M d, Y', strtotime($t['MATCHDATE'])) ?></div>
                            <div style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($t['MATCHTIME']) ?></div>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($t['VENUE']) ?></div>
                            <div style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($t['LOCATION'] ?? 'Main Ground') ?></div>
                        </td>
                        <td>
                            <strong style="color:#1b5e20;">Seat #<?= htmlspecialchars($t['SEATNO']) ?></strong>
                            <div style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($t['TICKET_TYPE'] ?: 'Standard') ?></div>
                        </td>
                        <td>
                            <strong style="font-size:0.95rem;">৳<?= number_format($t['PRICE'], 2) ?></strong>
                        </td>
                        <td>
                            <?php if ($isPaid): ?>
                                <span class="badge-status badge-status-paid">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    Paid
                                </span>
                            <?php else: ?>
                                <span class="badge-status badge-status-pending">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    Pending Approval
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isPaid): ?>
                                <a href="<?= BASE_URL ?>views/customer/payment.php?ticket_id=<?= $t['TICKETID'] ?>&match_id=<?= $t['MATCHID'] ?>&seat=<?= $t['SEATNO'] ?>&price=<?= $t['PRICE'] ?>" class="action-pay">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                    Pay Now
                                </a>
                            <?php else: ?>
                                <button onclick="window.print()" class="action-print">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    Print Ticket
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
