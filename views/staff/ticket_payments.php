<?php
session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') { 
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit(); 
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/helpers.php';
$conn = getOracleConnection();

$successMsg = '';
$errorMsg   = '';

// ── Handle Mark as Paid (POST) ────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_mark_paid'])) {
    $tid    = intval($_POST['ticket_id'] ?? 0);
    $method = trim($_POST['pay_method'] ?? 'Cash');
    if ($tid > 0) {
        $ok = oracleExecute($conn, 
            "UPDATE TICKET SET PAYMENT_STATUS='Paid', PAY_METHOD=:m WHERE TICKETID=:tid AND LOWER(PAYMENT_STATUS)='pending'",
            ['m' => $method, 'tid' => $tid]
        );
        $successMsg = $ok ? "Ticket #{$tid} has been successfully marked as <strong>Paid</strong> via {$method}." : "Could not update Ticket #{$tid}. It may already be Paid.";
    }
}

// ── Fetch all tickets (Pending first, then recent ID) ──
$tickets = oracleQuery($conn, "
    SELECT TK.TICKETID, TK.SEATNO, TK.TICKET_TYPE, TK.PRICE, TK.PAYMENT_STATUS, TK.PAY_METHOD,
           S.S_NAME, S.EMAIL AS S_EMAIL,
           HT.TEAMNAME||' vs '||AT.TEAMNAME AS MATCH_LABEL,
           HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM,
           TO_CHAR(M.MATCHDATE,'DD Mon YYYY') AS MATCH_DATE,
           M.MATCHTIME,
           V.V_NAME AS VENUE,
           T.T_NAME AS TOURNAMENT
    FROM TICKET TK
    JOIN SPECTATOR S ON TK.SPECTATORID=S.SPECTATORID
    JOIN MATCHES M   ON TK.MATCHID=M.MATCHID
    JOIN TEAM HT     ON M.HOMETEAMID=HT.TEAMID
    JOIN TEAM AT     ON M.AWAYTEAMID=AT.TEAMID
    JOIN VENUE V     ON M.VENUEID=V.VENUEID
    JOIN TOURNAMENT T ON M.TOURNAMENTID=T.TOURNAMENTID
    ORDER BY CASE WHEN LOWER(TK.PAYMENT_STATUS)='pending' THEN 0 ELSE 1 END, TK.TICKETID DESC
");

$pendingCount = count(array_filter($tickets, fn($t) => strtolower($t['PAYMENT_STATUS']) === 'pending'));
$totalCount   = count($tickets);
$currentPage  = 'tickets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Payments — Staff Portal — STMS</title>
    <meta name="description" content="Staff portal for verifying and processing ticket payment records. Mark pending tickets as paid and view payment details.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/sidebar.css">
    <style>
        .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;align-items:center;justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:var(--bg-card);border-radius:var(--radius-xl);padding:22px;width:500px;max-width:95vw;box-shadow:var(--shadow-lg);animation:slideUp .2s ease;max-height:90vh;overflow-y:auto; }
        @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
        .modal-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;border-bottom:1px solid var(--border);padding-bottom:10px; }
        .modal-title { font-size:1.05rem;font-weight:800;color:var(--text-primary);margin:0; }
        .modal-close { background:transparent;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);line-height:1; }
        .modal-close:hover { color:var(--text-primary); }

        .filter-tab { padding:5px 13px;border-radius:18px;font-size:0.75rem;font-weight:700;cursor:pointer;border:1px solid var(--border);background:transparent;color:var(--text-muted);transition:all .15s; }
        .filter-tab.active { background:var(--primary);color:#fff;border-color:var(--primary); }

        .detail-card { background:var(--bg-main);border:1px solid var(--border);border-radius:var(--radius);padding:12px;margin-bottom:12px; }
        .detail-row { display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.82rem; }
        .detail-row:last-child { margin-bottom:0; }
        .detail-label { color:var(--text-muted); }
        .detail-val { font-weight:600;color:var(--text-primary);text-align:right; }
        .detail-divider { height:1px;background:var(--border);margin:8px 0; }

        /* Compact Layout & Table Spacing */
        .main-content { padding: 18px 24px; }
        .page-header { margin-bottom: 12px; }
        .page-subtitle { font-size: 0.8rem; margin-top: 2px; }
        .card { margin-bottom: 14px; }
        .card-header { padding: 10px 18px; }

        #ticketsTable { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        #ticketsTable th {
            padding: 9px 12px;
            font-size: 0.70rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            text-align: left;
        }
        #ticketsTable td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: var(--text-primary);
            white-space: nowrap;
            line-height: 1.25;
        }
        #ticketsTable tbody tr {
            height: 44px;
            transition: background 0.12s ease;
        }
        #ticketsTable tbody tr:hover {
            background: #f8fafc;
        }
        #ticketsTable tbody tr:last-child td {
            border-bottom: none;
        }
        .text-ellipsis {
            max-width: 175px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
        }
        .btn-action-compact {
            padding: 3px 8px;
            font-size: 0.73rem;
            font-weight: 600;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            line-height: 1.2;
            cursor: pointer;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="portal-layout">
    <?php include __DIR__ . '/../../includes/staff_sidebar.php'; ?>
    <main class="main-content">

        <div class="topbar">
            <span class="topbar-title">STMS Staff Portal</span>
            <div class="topbar-right">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="ticketSearch" placeholder="Search ticket #, spectator, match..." oninput="searchTickets(this.value)">
                </div>
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'ST', 0, 2)) ?></div>
            </div>
        </div>

        <div class="page-header">
            <div class="breadcrumb">Staff / <span>Ticket Payments</span></div>
            <div class="page-header-row">
                <div>
                    <h1 style="font-size:1.25rem;">Ticket Payments</h1>
                    <p class="page-subtitle">
                        Verify and process ticket payment records. Total: <?= $totalCount ?> tickets
                        <?php if ($pendingCount > 0): ?>
                        (<strong><?= $pendingCount ?> pending verification</strong>)
                        <?php else: ?>
                        (all tickets paid)
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <?php if ($successMsg): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <?= $successMsg ?>
        </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        <div class="alert alert-danger" style="margin-bottom:16px;"><?= $errorMsg ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header" style="flex-wrap:wrap;gap:10px;">
                <span class="card-title">Ticket Records (<?= $totalCount ?>)</span>
                <div style="display:flex;gap:6px;">
                    <button type="button" class="filter-tab active" id="tab-all" onclick="filterTickets('', this)">All (<?= $totalCount ?>)</button>
                    <button type="button" class="filter-tab" id="tab-pending" onclick="filterTickets('Pending', this)">Pending (<?= $pendingCount ?>)</button>
                    <button type="button" class="filter-tab" id="tab-paid" onclick="filterTickets('Paid', this)">Paid (<?= $totalCount - $pendingCount ?>)</button>
                </div>
            </div>
            <div class="table-wrap">
                <table id="ticketsTable">
                    <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>Spectator</th>
                            <th>Match</th>
                            <th>Tournament</th>
                            <th>Date</th>
                            <th style="text-align:center;">Seat</th>
                            <th>Type</th>
                            <th>Price (৳)</th>
                            <th>Pay Method</th>
                            <th>Status</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="11" class="text-center text-muted" style="padding:24px;">No ticket records found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($tickets as $tk): 
                        $isPending = strtolower($tk['PAYMENT_STATUS']) === 'pending';
                        $subtotal  = floatval($tk['PRICE']);
                        $serviceFee = round($subtotal * 0.025, 2);
                        $vat        = round($subtotal * 0.05, 2);
                        $total      = $subtotal + $serviceFee + $vat;
                        $mDate      = trim($tk['MATCH_DATE']);
                    ?>
                    <tr data-status="<?= esc($tk['PAYMENT_STATUS']) ?>" data-search="<?= strtolower(esc($tk['TICKETID'] . ' ' . $tk['S_NAME'] . ' ' . $tk['S_EMAIL'] . ' ' . $tk['MATCH_LABEL'] . ' ' . $tk['SEATNO'] . ' ' . $tk['PAY_METHOD'])) ?>">
                        <td style="font-family:monospace;font-weight:700;color:var(--primary);font-size:0.82rem;">#TK-<?= str_pad($tk['TICKETID'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <div style="font-weight:700;font-size:0.85rem;line-height:1.2;"><?= esc($tk['S_NAME']) ?></div>
                            <?php if (!empty($tk['S_EMAIL'])): ?>
                            <div style="font-size:0.71rem;color:var(--text-muted);line-height:1;margin-top:1px;"><?= esc($tk['S_EMAIL']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:600;font-size:0.83rem;color:var(--text-primary);"><?= esc($tk['MATCH_LABEL']) ?></td>
                        <td><span class="text-ellipsis" title="<?= esc($tk['TOURNAMENT']) ?>"><?= esc($tk['TOURNAMENT']) ?></span></td>
                        <td style="font-variant-numeric:tabular-nums;color:var(--text-muted);font-size:0.81rem;"><?= esc($mDate) ?></td>
                        <td style="text-align:center;">
                            <span style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;padding:2px 7px;border-radius:4px;font-size:0.73rem;font-weight:700;">Seat <?= $tk['SEATNO'] ?></span>
                        </td>
                        <td><span class="badge badge-scheduled" style="font-size:0.68rem;padding:2px 7px;"><?= esc($tk['TICKET_TYPE']) ?></span></td>
                        <td style="font-weight:700;font-size:0.83rem;">৳ <?= number_format($tk['PRICE']) ?></td>
                        <td style="color:var(--text-muted);font-size:0.81rem;"><?= $tk['PAY_METHOD'] ? esc($tk['PAY_METHOD']) : '—' ?></td>
                        <td>
                            <span class="badge <?= $isPending ? 'badge-pending' : 'badge-paid' ?>" style="font-size:0.68rem;padding:2px 8px;">
                                <?= esc($tk['PAYMENT_STATUS']) ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex;gap:5px;align-items:center;justify-content:center;">
                                <button type="button" class="btn btn-outline btn-action-compact" title="View Payment & Ticket Details"
                                        onclick="openDetailModal(<?= htmlspecialchars(json_encode([
                                            'ticket_id'      => $tk['TICKETID'],
                                            'ticket_formatted'=> '#TK-' . str_pad($tk['TICKETID'], 5, '0', STR_PAD_LEFT),
                                            'spectator_name' => $tk['S_NAME'],
                                            'spectator_email'=> $tk['S_EMAIL'],
                                            'match_label'    => $tk['MATCH_LABEL'],
                                            'tournament'     => $tk['TOURNAMENT'],
                                            'venue'          => $tk['VENUE'] ?? 'Central Stadium',
                                            'match_date'     => trim($tk['MATCH_DATE']),
                                            'match_time'     => $tk['MATCHTIME'] ?? '02:30 PM',
                                            'seat_no'        => $tk['SEATNO'],
                                            'ticket_type'    => $tk['TICKET_TYPE'],
                                            'price'          => number_format($subtotal, 2),
                                            'service_fee'    => number_format($serviceFee, 2),
                                            'vat'            => number_format($vat, 2),
                                            'total'          => number_format($total, 2),
                                            'pay_method'     => $tk['PAY_METHOD'] ?: 'Cash',
                                            'status'         => $tk['PAYMENT_STATUS'],
                                            'is_pending'     => $isPending
                                        ]), ENT_QUOTES, 'UTF-8') ?>)">
                                    👁️ Details
                                </button>
                                <?php if ($isPending): ?>
                                <button type="button" class="btn btn-primary btn-action-compact" style="background:#166534;border-color:#166534;"
                                        onclick="openPayModal(<?= $tk['TICKETID'] ?>, '<?= esc($tk['S_NAME']) ?>', <?= $tk['SEATNO'] ?>, '<?= esc($tk['MATCH_LABEL']) ?>', '<?= number_format($total, 2) ?>')">
                                    💳 Mark Paid
                                </button>
                                <?php endif; ?>
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

<!-- ================= 1. Ticket & Payment Details Modal ================= -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="dModalTitle">Ticket Details</h3>
                <span id="dModalBadge" class="badge"></span>
            </div>
            <button type="button" class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>

        <div class="detail-card">
            <div class="detail-row">
                <span class="detail-label">Spectator:</span>
                <span class="detail-val" id="dSpectator">—</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-val" id="dEmail">—</span>
            </div>
            <div class="detail-divider"></div>
            <div class="detail-row">
                <span class="detail-label">Match:</span>
                <span class="detail-val" id="dMatch">—</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Tournament:</span>
                <span class="detail-val" id="dTournament">—</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Venue:</span>
                <span class="detail-val" id="dVenue">—</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date & Time:</span>
                <span class="detail-val" id="dDateTime">—</span>
            </div>
            <div class="detail-divider"></div>
            <div class="detail-row">
                <span class="detail-label">Seat & Category:</span>
                <span class="detail-val" id="dSeatCategory">—</span>
            </div>
        </div>

        <!-- Price Breakdown -->
        <div class="detail-card" style="background:#f0fdf4;border-color:#bbf7d0;">
            <div style="font-weight:700;color:#166534;margin-bottom:10px;font-size:0.9rem;">Payment Summary</div>
            <div class="detail-row">
                <span class="detail-label">Ticket Base Fare:</span>
                <span class="detail-val" id="dPrice">৳ 0.00</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Service Fee (2.5%):</span>
                <span class="detail-val" id="dFee">৳ 0.00</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">VAT (5%):</span>
                <span class="detail-val" id="dVat">৳ 0.00</span>
            </div>
            <div class="detail-divider" style="background:#bbf7d0;"></div>
            <div class="detail-row">
                <strong style="color:#0f172a;font-size:0.95rem;">Total Amount:</strong>
                <strong style="color:#166534;font-size:1.1rem;" id="dTotal">৳ 0.00</strong>
            </div>
            <div class="detail-divider" style="background:#bbf7d0;"></div>
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-val" id="dPayMethod">—</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Status:</span>
                <span class="detail-val" id="dStatus">—</span>
            </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;flex-wrap:wrap;">
            <button type="button" class="btn btn-outline" onclick="closeDetailModal()">Close</button>
            <button type="button" class="btn btn-outline" onclick="printModalReceipt()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Receipt
            </button>
            <div id="dModalPayAction" style="display:none;">
                <button type="button" class="btn btn-primary" id="dModalPayBtn">
                    💳 Mark as Paid
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= 2. Mark as Paid Modal ================= -->
<div class="modal-overlay" id="payModal">
    <div class="modal-box" style="width:460px;">
        <div class="modal-header">
            <h3 class="modal-title">💳 Confirm Payment</h3>
            <button type="button" class="modal-close" onclick="closePayModal()">&times;</button>
        </div>
        <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:16px;" id="payModalDesc">
            Mark this ticket as Paid?
        </p>
        <form method="POST">
            <input type="hidden" name="action_mark_paid" value="1">
            <input type="hidden" name="ticket_id" id="pay_ticket_id">
            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">
                    Select Payment Method <span style="color:#e53e3e;">*</span>
                </label>
                <select name="pay_method" id="pay_method" class="form-control" style="margin-top:6px;" required>
                    <option value="Cash" selected>Cash (Counter / Box Office)</option>
                    <option value="bKash">bKash</option>
                    <option value="Nagad">Nagad</option>
                    <option value="Rocket">Rocket</option>
                    <option value="Visa Card">Visa Card</option>
                    <option value="Mastercard">Mastercard</option>
                    <option value="SSLCommerz">SSLCommerz</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
                <small style="color:var(--text-muted);display:block;margin-top:6px;font-size:0.75rem;">
                    Spectators paying in physical cash at the ticketing booth can be recorded with "Cash".
                </small>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closePayModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">✓ Confirm Paid</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>js/main.js"></script>
<script>
let currentDetailData = null;

// ── Detail Modal ───────────────────────────────────
function openDetailModal(data) {
    currentDetailData = data;
    document.getElementById('dModalTitle').textContent = `Ticket ${data.ticket_formatted}`;
    
    const badge = document.getElementById('dModalBadge');
    badge.textContent = data.status;
    badge.className = 'badge ' + (data.is_pending ? 'badge-pending' : 'badge-paid');
    
    document.getElementById('dSpectator').textContent = data.spectator_name;
    document.getElementById('dEmail').textContent = data.spectator_email || '—';
    document.getElementById('dMatch').textContent = data.match_label;
    document.getElementById('dTournament').textContent = data.tournament;
    document.getElementById('dVenue').textContent = data.venue;
    document.getElementById('dDateTime').textContent = `${data.match_date} at ${data.match_time}`;
    document.getElementById('dSeatCategory').textContent = `Seat #${data.seat_no} (${data.ticket_type})`;
    
    document.getElementById('dPrice').textContent = `৳ ${data.price}`;
    document.getElementById('dFee').textContent = `৳ ${data.service_fee}`;
    document.getElementById('dVat').textContent = `৳ ${data.vat}`;
    document.getElementById('dTotal').textContent = `৳ ${data.total}`;
    document.getElementById('dPayMethod').textContent = data.pay_method;
    document.getElementById('dStatus').textContent = data.status;

    const payActionWrap = document.getElementById('dModalPayAction');
    if (data.is_pending) {
        payActionWrap.style.display = 'block';
        document.getElementById('dModalPayBtn').onclick = function() {
            closeDetailModal();
            openPayModal(data.ticket_id, data.spectator_name, data.seat_no, data.match_label, data.total);
        };
    } else {
        payActionWrap.style.display = 'none';
    }

    document.getElementById('detailModal').classList.add('open');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('open');
}

document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeDetailModal();
});

// ── Print Receipt from Modal ─────────────────────────
function printModalReceipt() {
    if (!currentDetailData) return;
    const d = currentDetailData;
    const w = window.open('', '_blank', 'width=460,height=600');
    w.document.write(`
        <!DOCTYPE html>
        <html><head><title>Ticket Receipt ${d.ticket_formatted}</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 24px; color: #1e293b; line-height: 1.4; }
            .box { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 22px; }
            h2 { margin: 0 0 4px 0; color: #15803d; font-size: 1.3rem; text-align: center; }
            .sub { text-align: center; font-size: 0.8rem; color: #64748b; margin-bottom: 14px; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 0.88rem; }
            td { padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
            .total-row td { border-top: 2px solid #cbd5e1; font-weight: bold; color: #15803d; font-size: 1rem; padding-top: 10px; }
            .footer { margin-top: 20px; font-size: 0.75rem; color: #94a3b8; text-align: center; }
        </style>
        </head><body>
        <div class="box">
            <h2>STMS University Sports</h2>
            <div class="sub">Official Admission Receipt &bull; ${d.ticket_formatted}</div>
            <table>
                <tr><td><strong>Spectator:</strong></td><td align="right">${d.spectator_name}</td></tr>
                <tr><td><strong>Tournament:</strong></td><td align="right">${d.tournament}</td></tr>
                <tr><td><strong>Match:</strong></td><td align="right">${d.match_label}</td></tr>
                <tr><td><strong>Venue:</strong></td><td align="right">${d.venue}</td></tr>
                <tr><td><strong>Date & Time:</strong></td><td align="right">${d.match_date} | ${d.match_time}</td></tr>
                <tr><td><strong>Seat & Category:</strong></td><td align="right">Seat #${d.seat_no} (${d.ticket_type})</td></tr>
                <tr><td>Ticket Fare:</td><td align="right">৳ ${d.price}</td></tr>
                <tr><td>Service Fee (2.5%):</td><td align="right">৳ ${d.service_fee}</td></tr>
                <tr><td>VAT (5%):</td><td align="right">৳ ${d.vat}</td></tr>
                <tr class="total-row"><td>Total Amount:</td><td align="right">৳ ${d.total}</td></tr>
                <tr><td>Payment Method:</td><td align="right">${d.pay_method}</td></tr>
                <tr><td>Status:</td><td align="right"><strong>${d.status}</strong></td></tr>
            </table>
            <div class="footer">
                Printed from STMS Staff Desk &bull; Thank you for supporting university sports!
            </div>
        </div>
        <script>window.print();<\/script>
        </body></html>
    `);
    w.document.close();
}

// ── Pay Modal ──────────────────────────────────────
function openPayModal(ticketId, name, seat, match, total) {
    document.getElementById('pay_ticket_id').value = ticketId;
    document.getElementById('payModalDesc').innerHTML = 
        `Mark Ticket <strong>#TK-${String(ticketId).padStart(5, '0')}</strong> (Seat ${seat}) for <em>"${match}"</em><br>Spectator: <strong>${name}</strong> as <strong>Paid</strong>?`;
    document.getElementById('payModal').classList.add('open');
}
function closePayModal() {
    document.getElementById('payModal').classList.remove('open');
}
document.getElementById('payModal').addEventListener('click', function(e) {
    if (e.target === this) closePayModal();
});

// ── Filter Tabs ────────────────────────────────────
function filterTickets(status, btn) {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
}

// ── Search Tickets ─────────────────────────────────
function searchTickets(query) {
    applyFilters();
}

function applyFilters() {
    const activeTab = document.querySelector('.filter-tab.active');
    let statusFilter = '';
    if (activeTab.id === 'tab-pending') statusFilter = 'pending';
    if (activeTab.id === 'tab-paid')    statusFilter = 'paid';

    const q = (document.getElementById('ticketSearch').value || '').trim().toLowerCase();

    document.querySelectorAll('#ticketsTable tbody tr').forEach(r => {
        const rStatus = (r.dataset.status || '').toLowerCase();
        const rSearch = (r.dataset.search || '').toLowerCase();

        const statusMatch = !statusFilter || rStatus === statusFilter;
        const searchMatch = !q || rSearch.includes(q);

        r.style.display = (statusMatch && searchMatch) ? '' : 'none';
    });
}
</script>
</body>
</html>
