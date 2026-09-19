<?php
// views/admin/payment_recording.php
// Unified Ticket Sales & Payment Ledger + Stadium Box Office

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); 
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';
$conn = getOracleConnection();

$successMsg = ''; 
$errorMsg   = '';

// ── Handle Approve / Mark as Paid ───────────────────────
if (isset($_GET['pay'])) {
    $tid = intval($_GET['pay']);
    if ($tid > 0) {
        oracleExecute($conn, "UPDATE TICKET SET PAYMENT_STATUS = 'Paid' WHERE TICKETID = :tid", ['tid' => $tid]);
        // Also update or insert payment record
        $pCheck = oracleQuery($conn, "SELECT PAYMENTID FROM PAYMENT WHERE TICKETID = :tid", ['tid' => $tid]);
        if (!empty($pCheck)) {
            oracleExecute($conn, "UPDATE PAYMENT SET PAYMENTSTATUS = 'Success' WHERE TICKETID = :tid", ['tid' => $tid]);
        } else {
            $tInfo = oracleQuery($conn, "SELECT PRICE, PAY_METHOD FROM TICKET WHERE TICKETID = :tid", ['tid' => $tid]);
            $price = $tInfo[0]['PRICE'] ?? 200;
            $meth  = $tInfo[0]['PAY_METHOD'] ?? 'Cash';
            oracleExecute($conn, "
                INSERT INTO PAYMENT (PaymentID, TicketID, Amount, PaymentDate, PaymentMethod, PaymentStatus)
                VALUES (seq_payment.NEXTVAL, :tid, :amount, CURRENT_TIMESTAMP, :pmethod, 'Success')
            ", ['tid' => $tid, 'amount' => $price, 'pmethod' => $meth]);
        }
        header('Location: ' . BASE_URL . 'views/admin/payment_recording.php?msg=paid');
        exit();
    }
}

// ── Handle Cancel / Delete Ticket ───────────────────────
if (isset($_GET['cancel_ticket'])) {
    $cancelId = intval($_GET['cancel_ticket']);
    if ($cancelId > 0) {
        oracleExecute($conn, "DELETE FROM PAYMENT WHERE TICKETID = :tid", ['tid' => $cancelId]);
        $del = oracleExecute($conn, "DELETE FROM TICKET WHERE TICKETID = :tid", ['tid' => $cancelId]);
        if ($del) {
            header("Location: " . BASE_URL . "views/admin/payment_recording.php?msg=cancelled");
            exit();
        } else {
            $errorMsg = "Could not cancel ticket #{$cancelId}.";
        }
    }
}

// ── Handle Walk-in Box Office Ticket POST ───────────────
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'issue_ticket') {
    $matchId     = intval($_POST['match_id'] ?? 0);
    $spectatorId = intval($_POST['spectator_id'] ?? 0);
    $seatNo      = intval($_POST['seat_no'] ?? 0);
    $gallery     = trim($_POST['gallery_block'] ?? 'Standard');
    $price       = floatval($_POST['price'] ?? 200);
    $payMethod   = trim($_POST['pay_method'] ?? 'Cash (Counter)');
    $payStatus   = trim($_POST['pay_status'] ?? 'Paid');

    if ($matchId && $spectatorId && $seatNo > 0) {
        $check = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TICKET WHERE MATCHID = :mid AND SEATNO = :sno", ['mid' => $matchId, 'sno' => $seatNo]);
        if (($check[0]['CNT'] ?? 0) > 0) {
            $errorMsg = "Seat #{$seatNo} is already booked for this match. Please choose another seat.";
        } else {
            $ok = oracleExecute($conn, "
                INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method)
                VALUES (seq_ticket.NEXTVAL, :mid, :sid, :sno, :ttype, :price, :pstatus, :pmethod)
            ", [
                'mid'     => $matchId,
                'sid'     => $spectatorId,
                'sno'     => $seatNo,
                'ttype'   => $gallery,
                'price'   => $price,
                'pstatus' => $payStatus,
                'pmethod' => $payMethod
            ]);

            if ($ok) {
                $newT = oracleQuery($conn, "SELECT MAX(TICKETID) AS TID FROM TICKET WHERE MATCHID=:mid AND SEATNO=:sno", ['mid' => $matchId, 'sno' => $seatNo]);
                $newTid = $newT[0]['TID'] ?? null;
                if ($newTid && $payStatus === 'Paid') {
                    oracleExecute($conn, "
                        INSERT INTO PAYMENT (PaymentID, TicketID, Amount, PaymentDate, PaymentMethod, PaymentStatus)
                        VALUES (seq_payment.NEXTVAL, :tid, :amount, CURRENT_TIMESTAMP, :pmethod, 'Success')
                    ", [
                        'tid'     => $newTid,
                        'amount'  => $price,
                        'pmethod' => $payMethod
                    ]);
                }
                header("Location: " . BASE_URL . "views/admin/payment_recording.php?msg=issued");
                exit();
            } else {
                $errorMsg = "Could not issue ticket. Please verify inputs.";
            }
        }
    } else {
        $errorMsg = "Please fill all required fields (Match, Spectator, Seat No).";
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'paid')      $successMsg = 'Payment approved and ticket marked as Paid!';
    if ($_GET['msg'] === 'cancelled') $successMsg = 'Ticket cancelled and seat released successfully.';
    if ($_GET['msg'] === 'issued')    $successMsg = 'Walk-in ticket issued successfully at stadium box office!';
}

// ── KPI Stats ──────────────────────────────────────────
$totalTickets = count(oracleQuery($conn, "SELECT TICKETID FROM TICKET"));
$revData      = oracleQuery($conn, "SELECT SUM(PRICE) AS TOTAL_REV FROM TICKET WHERE PAYMENT_STATUS = 'Paid'");
$totalRev     = $revData[0]['TOTAL_REV'] ?? 0;
$pendingRev   = oracleQuery($conn, "SELECT SUM(PRICE) AS PENDING_REV FROM TICKET WHERE LOWER(PAYMENT_STATUS) != 'paid'")[0]['PENDING_REV'] ?? 0;
$pendingCount = count(oracleQuery($conn, "SELECT TICKETID FROM TICKET WHERE LOWER(PAYMENT_STATUS) != 'paid'"));
$paidCount    = count(oracleQuery($conn, "SELECT TICKETID FROM TICKET WHERE PAYMENT_STATUS = 'Paid'"));

// ── Filter & Search ────────────────────────────────────
$filterStatus = trim($_GET['status'] ?? '');
$searchQuery  = trim($_GET['q'] ?? '');

$sql = "
    SELECT TK.TICKETID, TK.PRICE, TK.PAYMENT_STATUS, TK.PAY_METHOD, TK.SEATNO, TK.TICKET_TYPE,
           S.S_NAME AS SPECTATOR_NAME, S.EMAIL AS SPECTATOR_EMAIL,
           HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM,
           M.MATCHDATE, M.MATCHTIME, V.V_NAME AS VENUE
    FROM TICKET TK
    JOIN SPECTATOR S ON TK.SPECTATORID = S.SPECTATORID
    JOIN MATCHES M   ON TK.MATCHID = M.MATCHID
    JOIN TEAM HT     ON M.HOMETEAMID = HT.TEAMID
    JOIN TEAM AT     ON M.AWAYTEAMID = AT.TEAMID
    JOIN VENUE V     ON M.VENUEID = V.VENUEID
    WHERE 1=1
";
$params = [];
if (!empty($filterStatus)) {
    $sql .= " AND LOWER(TK.PAYMENT_STATUS) = LOWER(:fstatus)";
    $params['fstatus'] = $filterStatus;
}
if (!empty($searchQuery)) {
    $sql .= " AND (LOWER(S.S_NAME) LIKE LOWER(:q1) OR LOWER(HT.TEAMNAME) LIKE LOWER(:q2) OR LOWER(AT.TEAMNAME) LIKE LOWER(:q3) OR CAST(TK.TICKETID AS TEXT) LIKE :q4)";
    $qWild = '%' . $searchQuery . '%';
    $params['q1'] = $qWild;
    $params['q2'] = $qWild;
    $params['q3'] = $qWild;
    $params['q4'] = $qWild;
}
$sql .= " ORDER BY TK.TICKETID DESC";

$payments = oracleQuery($conn, $sql, $params);

// Matches & Spectators for walk-in form
$upcomingMatches = oracleQuery($conn, "
    SELECT M.MATCHID, HT.TEAMNAME AS HOME, AT.TEAMNAME AS AWAY, M.MATCHDATE, M.MATCHTIME, V.V_NAME AS VENUE
    FROM MATCHES M
    JOIN TEAM HT ON M.HOMETEAMID = HT.TEAMID
    JOIN TEAM AT ON M.AWAYTEAMID = AT.TEAMID
    JOIN VENUE V ON M.VENUEID = V.VENUEID
    WHERE M.MATCHSTATUS IN ('Scheduled', 'Confirmed')
    ORDER BY M.MATCHDATE ASC
");
$spectators = oracleQuery($conn, "SELECT SPECTATORID, S_NAME, EMAIL FROM SPECTATOR ORDER BY S_NAME");

$currentPage = 'payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Sales & Payments — STMS Admin</title>
    <meta name="description" content="Manage match ticket sales, revenue collection, customer bookings, and box office issuance.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/forms.css">
    <style>
        .mark-paid-btn {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 5px 12px;
            border-radius: var(--radius);
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .mark-paid-btn:hover { background: var(--primary-hover); }
    </style>
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>
    <main class="main-content">

        <!-- Topbar -->
        <div class="topbar">
            <span class="topbar-title">Admin Portal — STMS</span>
            <div class="topbar-right">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'AD', 0, 2)) ?></div>
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="breadcrumb" style="margin-bottom:8px;">
                <a href="<?= BASE_URL ?>views/admin/dashboard.php">Admin</a>
                <span style="margin:0 6px;color:var(--text-muted);">›</span>
                <span>Ticket Sales & Payments</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-size:1.4rem;font-weight:800;">Ticket Sales & Payments</h1>
                    <p class="page-subtitle">Unified ledger tracking all customer ticket bookings, revenue collection, and stadium box-office sales.</p>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="toggleWalkInForm()">
                        + Issue Walk-in Ticket (Booth)
                    </button>
                </div>
            </div>
        </div>

        <?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg):   ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

        <!-- KPI Grid -->
        <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Total Revenue Collected</span>
                    <div class="kpi-icon" style="color:#28a745;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                </div>
                <div class="kpi-value text-green">৳<?= number_format($totalRev) ?></div>
                <span class="kpi-sub positive">From verified tickets</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Pending Payments</span>
                    <div class="kpi-icon" style="color:#d97706;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                </div>
                <div class="kpi-value text-orange">৳<?= number_format($pendingRev) ?></div>
                <span class="kpi-sub warn"><?= $pendingCount ?> tickets awaiting payment</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Total Tickets Sold</span>
                    <div class="kpi-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
                </div>
                <div class="kpi-value"><?= $totalTickets ?></div>
                <span class="kpi-sub">Across all venues</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label">Confirmed (Paid)</span>
                    <div class="kpi-icon" style="color:#28a745;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
                </div>
                <div class="kpi-value text-green"><?= $paidCount ?></div>
                <span class="kpi-sub positive">Admissions verified</span>
            </div>
        </div>

        <!-- Box Office Walk-in Form (Collapsible) -->
        <div class="card" id="walkInFormCard" style="display:none;margin-bottom:24px;border:1px solid var(--border);">
            <div class="card-header" style="background:var(--bg-table-header);">
                <div>
                    <span class="card-title">🎟️ Stadium Box Office — Issue Over-The-Counter Ticket</span>
                    <div class="text-xs text-muted" style="margin-top:2px;">Use this form when an offline spectator visits the stadium ticket counter.</div>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="toggleWalkInForm()">✕ Close</button>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="issue_ticket">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Match Fixture *</label>
                            <select name="match_id" class="form-control" required>
                                <option value="">Select Scheduled Match</option>
                                <?php foreach ($upcomingMatches as $um): ?>
                                <option value="<?= $um['MATCHID'] ?>">
                                    <?= esc($um['HOME']) ?> vs <?= esc($um['AWAY']) ?> — <?= date('M d', strtotime($um['MATCHDATE'])) ?> (<?= esc($um['VENUE']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Spectator / Customer Account *</label>
                            <select name="spectator_id" class="form-control" required>
                                <option value="">Select Registered Spectator</option>
                                <?php foreach ($spectators as $sp): ?>
                                <option value="<?= $sp['SPECTATORID'] ?>"><?= esc($sp['S_NAME']) ?> (<?= esc($sp['EMAIL']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Gallery Block *</label>
                            <select name="gallery_block" id="galleryBlock" class="form-control" onchange="calcAmount()" required>
                                <option value="Standard" data-price="200">Standard / North (৳200)</option>
                                <option value="Grand" data-price="300">Grand Stand (৳300)</option>
                                <option value="VIP" data-price="600">VIP Pavilion (৳600)</option>
                                <option value="South" data-price="200">South Stand (৳200)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Seat Number (Integer) *</label>
                            <input type="number" name="seat_no" class="form-control" placeholder="e.g. 15" min="1" max="500" required>
                        </div>
                        <div class="form-group">
                            <label>Price (BDT)</label>
                            <input type="number" name="price" id="ticketPrice" class="form-control" value="200" readonly style="background:var(--bg-table-header);">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="pay_method" class="form-control">
                                <option value="Cash (Counter)">Cash (Over-The-Counter)</option>
                                <option value="Card (POS)">Card (POS Terminal)</option>
                                <option value="bKash (QR)">bKash (Counter QR)</option>
                                <option value="Nagad">Nagad</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Status</label>
                            <select name="pay_status" class="form-control">
                                <option value="Paid">Paid (Cash Received)</option>
                                <option value="Pending">Pending (Pay at Gate)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                        <button type="button" class="btn btn-outline" onclick="toggleWalkInForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm & Issue Ticket</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card" style="margin-bottom:20px;padding:14px 20px;">
            <form method="GET" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:center;gap:10px;flex:1;max-width:400px;">
                    <input type="text" name="q" class="form-control" placeholder="Search spectator, team, ticket ID..." value="<?= esc($searchQuery) ?>" style="margin:0;">
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <label style="font-size:0.82rem;color:var(--text-muted);margin:0;">Status:</label>
                    <select name="status" class="form-control" style="width:auto;margin:0;" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="Paid" <?= ($filterStatus === 'Paid') ? 'selected' : '' ?>>Paid (Confirmed)</option>
                        <option value="Pending" <?= ($filterStatus === 'Pending') ? 'selected' : '' ?>>Pending Approval</option>
                    </select>
                    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
                    <?php if ($searchQuery || $filterStatus): ?>
                    <a href="<?= BASE_URL ?>views/admin/payment_recording.php" class="btn btn-ghost btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Ledger Table -->
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Recent Ticket Sales &amp; Payment Approvals</span>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin:2px 0 0 0;">Review incoming spectator payments and instantly approve pending bookings.</p>
                </div>
                <span class="text-muted text-sm"><?= count($payments) ?> records total</span>
            </div>
            <div class="table-wrap">
                <table id="paymentsTable">
                    <thead>
                        <tr>
                            <th>TICKET</th>
                            <th>CUSTOMER</th>
                            <th>MATCH</th>
                            <th>SEAT</th>
                            <th>AMOUNT</th>
                            <th>PAYMENT METHOD</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">No payment records found matching your filters.</td></tr>
                    <?php else: ?>
                    <?php foreach ($payments as $p):
                        $isPending = strtolower($p['PAYMENT_STATUS']) === 'pending';
                    ?>
                    <tr>
                        <td class="font-bold">
                            <strong style="color:var(--primary);">#TK-<?= str_pad($p['TICKETID'], 5, '0', STR_PAD_LEFT) ?></strong>
                        </td>
                        <td>
                            <div style="font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($p['SPECTATOR_NAME']) ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars($p['SPECTATOR_EMAIL']) ?></div>
                        </td>
                        <td>
                            <strong class="text-sm"><?= htmlspecialchars($p['HOME_TEAM']) ?> vs <?= htmlspecialchars($p['AWAY_TEAM']) ?></strong>
                            <div class="text-xs text-muted"><?= date('M d, Y', strtotime($p['MATCHDATE'])) ?> at <?= htmlspecialchars($p['MATCHTIME']) ?> &bull; <?= htmlspecialchars($p['VENUE']) ?></div>
                        </td>
                        <td>
                            <span class="badge badge-scheduled" style="font-weight:700;font-size:0.75rem;">SEAT #<?= $p['SEATNO'] ?></span>
                        </td>
                        <td><strong>৳<?= number_format($p['PRICE'], 2) ?></strong></td>
                        <td><span class="text-sm font-semibold"><?= htmlspecialchars($p['PAY_METHOD'] ?: 'Pending') ?></span></td>
                        <td>
                            <span class="badge <?= $isPending ? 'badge-pending' : 'badge-paid' ?>">
                                <?= strtoupper(htmlspecialchars($p['PAYMENT_STATUS'])) ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <?php if ($isPending): ?>
                                <a href="<?= BASE_URL ?>views/admin/payment_recording.php?pay=<?= $p['TICKETID'] ?>" class="btn btn-primary btn-sm" style="background:#1b5e20;color:#fff;border-radius:6px;padding:4px 12px;font-size:0.75rem;font-weight:700;" onclick="return confirm('Confirm payment receipt for Ticket #<?= $p['TICKETID'] ?>?');">Approve</a>
                                <?php else: ?>
                                <span class="text-green text-sm font-bold" style="color:#166534;font-size:0.82rem;">✓ Approved</span>
                                <?php endif; ?>
                                <a href="javascript:void(0)" class="btn btn-ghost btn-sm" title="Print Receipt" onclick="printReceipt('<?= $p['TICKETID'] ?>', '<?= addslashes($p['SPECTATOR_NAME']) ?>', '<?= addslashes($p['HOME_TEAM'] . ' vs ' . $p['AWAY_TEAM']) ?>', '<?= $p['PRICE'] ?>', '<?= $p['SEATNO'] ?>', '<?= $p['PAYMENT_STATUS'] ?>')">
                                    🖨️
                                </a>
                                <a href="<?= BASE_URL ?>views/admin/payment_recording.php?cancel_ticket=<?= $p['TICKETID'] ?>"
                                   class="btn btn-ghost btn-sm" style="color:#ef4444;font-weight:700;"
                                   title="Cancel Ticket" onclick="return confirm('Cancel Ticket #<?= $p['TICKETID'] ?> and release the seat?');">
                                   ✕
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script src="<?= BASE_URL ?>js/main.js"></script>
<script>
function toggleWalkInForm() {
    const card = document.getElementById('walkInFormCard');
    if (card.style.display === 'none' || !card.style.display) {
        card.style.display = 'block';
        card.scrollIntoView({ behavior: 'smooth' });
    } else {
        card.style.display = 'none';
    }
}

function calcAmount() {
    const sel = document.getElementById('galleryBlock');
    const price = sel.options[sel.selectedIndex].getAttribute('data-price') || 200;
    document.getElementById('ticketPrice').value = price;
}

function printReceipt(tid, name, match, price, seat, status) {
    const w = window.open('', '_blank', 'width=450,height=550');
    w.document.write(`
        <html><head><title>Receipt #TK-${tid}</title>
        <style>body{font-family:sans-serif;padding:24px;color:#1e293b;} .box{border:2px dashed #cbd5e1;border-radius:8px;padding:20px;text-align:center;} h2{margin:0 0 6px 0;color:#15803d;} table{width:100%;margin-top:16px;text-align:left;border-collapse:collapse;} td{padding:6px 0;border-bottom:1px solid #f1f5f9;}</style>
        </head><body>
        <div class="box">
            <h2>STMS University Sports</h2>
            <div style="font-size:0.85rem;color:#64748b;">Official Match Admission Receipt</div>
            <hr style="border:none;border-top:1px solid #e2e8f0;margin:12px 0;">
            <table>
                <tr><td><strong>Ticket ID:</strong></td><td>#TK-${tid}</td></tr>
                <tr><td><strong>Spectator:</strong></td><td>${name}</td></tr>
                <tr><td><strong>Match:</strong></td><td>${match}</td></tr>
                <tr><td><strong>Seat Number:</strong></td><td>Seat #${seat}</td></tr>
                <tr><td><strong>Amount Paid:</strong></td><td><strong>৳${price}</strong></td></tr>
                <tr><td><strong>Status:</strong></td><td><span style="color:#16a34a;font-weight:700;">${status}</span></td></tr>
            </table>
            <div style="margin-top:20px;font-size:0.8rem;color:#94a3b8;">Printed from STMS Admin Terminal</div>
        </div>
        <script>window.print();<\/script>
        </body></html>
    `);
    w.document.close();
}
</script>
</body>
</html>
