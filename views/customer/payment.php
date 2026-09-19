<?php
// views/customer/payment.php
// Figma: "Payment - STMS Customer Portal.png"

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn = getOracleConnection();

$spectatorId = $_SESSION['user_id'] ?? 0;
$spectatorName = $_SESSION['name'] ?? 'Daud Ibrahim';

$matchId  = intval($_GET['match_id'] ?? ($_POST['match_id'] ?? 0));
$seatNo   = intval($_GET['seat'] ?? ($_POST['seat_no'] ?? 14));
$ticketId = intval($_GET['ticket_id'] ?? ($_POST['ticket_id'] ?? 0));
$price    = floatval($_GET['price'] ?? ($_POST['price'] ?? 2400));
$categoryName = 'Grand Stand - North';

// If paying for an existing ticket, fetch its details from DB
if ($ticketId) {
    $tRows = oracleQuery($conn, "SELECT * FROM TICKET WHERE TICKETID = :tid", ['tid' => $ticketId]);
    if (!empty($tRows)) {
        $t = $tRows[0];
        $matchId = intval($t['MATCHID']);
        $seatNo = intval($t['SEATNO']);
        $price = floatval($t['PRICE']);
        if (!empty($t['TICKET_TYPE'])) $categoryName = $t['TICKET_TYPE'];
    }
}

// Fetch Match and Tournament Details
$match = null;
if ($matchId) {
    $rows = oracleQuery($conn, "
        SELECT M.MATCHID, M.MATCHDATE, M.MATCHTIME,
               HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM,
               V.V_NAME AS VENUE, T.T_NAME AS TOURNAMENT, T.SPORT_TYPE
        FROM MATCHES M
        JOIN TEAM HT ON M.HOMETEAMID=HT.TEAMID
        JOIN TEAM AT ON M.AWAYTEAMID=AT.TEAMID
        JOIN VENUE V  ON M.VENUEID=V.VENUEID
        JOIN TOURNAMENT T ON M.TOURNAMENTID=T.TOURNAMENTID
        WHERE M.MATCHID = :mid
    ", ['mid' => $matchId]);
    if (!empty($rows)) {
        $match = $rows[0];
    }
}

// Fallback demo match details if not in DB yet
$tournamentName = $match['TOURNAMENT'] ?? 'Inter-University Cricket League 2024';
$matchDateStr   = !empty($match['MATCHDATE']) ? date('F d, Y', strtotime($match['MATCHDATE'])) : 'October 24, 2024';
$matchTimeStr   = !empty($match['MATCHTIME']) ? $match['MATCHTIME'] : '02:30 PM';
$ticketsCount   = $seatNo ? sprintf('01 Person (Seat #%02d)', $seatNo) : '02 Persons';

// Fee calculation matching Figma
$subtotal   = ($price > 0) ? $price : 2400.00;
$serviceFee = round($subtotal * 0.025, 2);
$vat        = round($subtotal * 0.05, 2);
$totalAmount = $subtotal + $serviceFee + $vat;

$paymentSuccess = false;
$txnId = '';
$errorMsg = '';

// Handle Payment Confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_confirm_payment'])) {
    $payMethod = trim($_POST['pay_method'] ?? 'SSLCommerz');
    $txnId = 'STMS-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));

    $updated = false;
    if ($ticketId) {
        $updated = oracleExecute($conn, "UPDATE TICKET SET PAYMENT_STATUS = 'Paid', PAY_METHOD = :pm WHERE TICKETID = :tid", [
            'pm'  => $payMethod,
            'tid' => $ticketId
        ]);
    }

    if (!$updated && $matchId && $seatNo) {
        $check = oracleQuery($conn, "SELECT TICKETID FROM TICKET WHERE MATCHID = :mid AND SEATNO = :sno", ['mid' => $matchId, 'sno' => $seatNo]);
        if (!empty($check)) {
            $updated = oracleExecute($conn, "UPDATE TICKET SET PAYMENT_STATUS = 'Paid', PAY_METHOD = :pm, SPECTATORID = :sid WHERE TICKETID = :tid", [
                'pm'  => $payMethod,
                'sid' => $spectatorId,
                'tid' => $check[0]['TICKETID']
            ]);
        } else {
            $updated = oracleExecute($conn, "INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method) VALUES (seq_ticket.NEXTVAL, :mid, :sid, :sno, :ttype, :pr, 'Paid', :pm)", [
                'mid'   => $matchId,
                'sid'   => $spectatorId,
                'sno'   => $seatNo,
                'ttype' => $categoryName,
                'pr'    => $subtotal,
                'pm'    => $payMethod
            ]);
        }
    }

    $paymentSuccess = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment & Checkout — STMS Customer Portal</title>
    <meta name="description" content="Secure payment gateway for booking sports tournament tickets with SSLCommerz, bKash, Nagad, Visa, and Mastercard.">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/customer.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/forms.css">
</head>
<body class="checkout-page">

    <!-- Topbar matching Figma: BACK | Sports Tournament Management System | SECURE CHECKOUT | EXIT -->
    <header class="checkout-topbar">
        <a href="<?= BASE_URL ?>views/customer/<?= $ticketId ? 'my_tickets.php' : 'book_ticket.php' . ($matchId ? '?match_id='.$matchId : '') ?>" class="checkout-back-link">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            BACK
        </a>
        <div class="checkout-title">Sports Tournament Management System</div>
        <div style="display:flex;align-items:center;gap:14px;">
            <div class="checkout-secure-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                SECURE CHECKOUT
            </div>
            <a href="<?= BASE_URL ?>controllers/AuthController.php?action=logout" style="display:inline-flex;align-items:center;gap:6px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:6px 12px;border-radius:6px;font-size:0.78rem;font-weight:700;text-decoration:none;" title="Exit / Logout">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Exit
            </a>
        </div>
    </header>

    <?php if ($paymentSuccess): ?>
    <!-- ================= Payment Success State ================= -->
    <main class="customer-content" style="max-width:650px;">
        <div class="payment-success-card">
            <div class="success-icon-circle">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <h2 style="font-size:1.5rem;font-weight:800;color:#0f172a;margin-bottom:6px;">Payment Successful!</h2>
            <p style="font-size:0.88rem;color:#64748b;margin-bottom:20px;">
                Your ticket has been confirmed and registered in the database.
            </p>

            <div class="receipt-box">
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:0.8rem;color:#64748b;">Transaction ID:</span>
                    <strong style="font-size:0.85rem;color:#0f172a;"><?= htmlspecialchars($txnId) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:0.8rem;color:#64748b;">Tournament:</span>
                    <strong style="font-size:0.85rem;color:#0f172a;"><?= htmlspecialchars($tournamentName) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:0.8rem;color:#64748b;">Seat & Category:</span>
                    <strong style="font-size:0.85rem;color:#166534;">Seat #<?= htmlspecialchars($seatNo) ?> (<?= htmlspecialchars($categoryName) ?>)</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:0.8rem;color:#64748b;">Date & Time:</span>
                    <span style="font-size:0.85rem;color:#0f172a;"><?= $matchDateStr ?> | <?= $matchTimeStr ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:0.8rem;color:#64748b;">Payment Method:</span>
                    <span style="font-size:0.85rem;color:#0f172a;"><?= htmlspecialchars($payMethod) ?></span>
                </div>
                <div class="summary-divider"></div>
                <div style="display:flex;justify-content:space-between;">
                    <strong style="font-size:0.95rem;color:#0f172a;">Total Paid:</strong>
                    <strong style="font-size:1.15rem;color:#166534;">৳ <?= number_format($totalAmount, 2) ?></strong>
                </div>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
                <button onclick="window.print()" class="btn btn-outline" style="display:flex;align-items:center;gap:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Print Ticket
                </button>
                <a href="<?= BASE_URL ?>views/customer/my_tickets.php" class="btn btn-primary" style="display:flex;align-items:center;gap:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    View in My Bookings
                </a>
                <a href="<?= BASE_URL ?>views/customer/home.php" class="btn btn-outline" style="display:flex;align-items:center;gap:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>
    </main>

    <?php else: ?>
    <!-- ================= Main Checkout Form (Figma Exact) ================= -->
    <div class="checkout-container">

        <!-- Left Column: Payment Method + Payment Details -->
        <div class="checkout-left">
            <form id="paymentForm" action="<?= BASE_URL ?>views/customer/payment.php" method="POST">
                <input type="hidden" name="action_confirm_payment" value="1">
                <input type="hidden" name="match_id" value="<?= htmlspecialchars($matchId) ?>">
                <input type="hidden" name="seat_no" value="<?= htmlspecialchars($seatNo) ?>">
                <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($ticketId) ?>">
                <input type="hidden" name="price" value="<?= htmlspecialchars($subtotal) ?>">
                <input type="hidden" name="pay_method" id="selectedPayMethod" value="SSLCommerz">

                <!-- Card 1: Select Payment Method -->
                <div class="checkout-card">
                    <h2 class="checkout-section-title">Select Payment Method</h2>
                    <div class="payment-methods-grid">
                        
                        <!-- 1. SSLCommerz -->
                        <div class="payment-method-item active" data-method="SSLCommerz" onclick="selectPaymentMethod('SSLCommerz', this)">
                            <div class="payment-method-logo" style="background:#e8f5e9;color:#1b5e20;">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="3" y1="21" x2="21" y2="21"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                    <polyline points="5 6 12 3 19 6"></polyline>
                                    <line x1="4" y1="10" x2="4" y2="21"></line>
                                    <line x1="20" y1="10" x2="20" y2="21"></line>
                                    <line x1="8" y1="14" x2="8" y2="17"></line>
                                    <line x1="12" y1="14" x2="12" y2="17"></line>
                                    <line x1="16" y1="14" x2="16" y2="17"></line>
                                </svg>
                            </div>
                            <span class="payment-method-name">SSLCommerz</span>
                        </div>

                        <!-- 2. bKash -->
                        <div class="payment-method-item" data-method="bKash" onclick="selectPaymentMethod('bKash', this)">
                            <div class="payment-method-logo" style="background:#fce7f3;color:#db2777;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z"/>
                                </svg>
                            </div>
                            <span class="payment-method-name">bKash</span>
                        </div>

                        <!-- 3. Nagad -->
                        <div class="payment-method-item" data-method="Nagad" onclick="selectPaymentMethod('Nagad', this)">
                            <div class="payment-method-logo" style="background:#fff7ed;color:#ea580c;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2L2 7l10 5 10-5-10-5zm0 9l-8-4v8l8 4 8-4v-8l-8 4z"/>
                                </svg>
                            </div>
                            <span class="payment-method-name">Nagad</span>
                        </div>

                        <!-- 4. Visa Card -->
                        <div class="payment-method-item" data-method="Visa Card" onclick="selectPaymentMethod('Visa Card', this)">
                            <div class="payment-method-logo" style="background:#eff6ff;color:#1d4ed8;">
                                <svg width="28" height="20" viewBox="0 0 36 24" fill="currentColor">
                                    <rect width="36" height="24" rx="3" fill="#1e40af"/>
                                    <text x="5" y="16" fill="#ffffff" font-weight="900" font-size="12" font-family="sans-serif">VISA</text>
                                </svg>
                            </div>
                            <span class="payment-method-name">Visa Card</span>
                        </div>

                        <!-- 5. Mastercard -->
                        <div class="payment-method-item" data-method="Mastercard" onclick="selectPaymentMethod('Mastercard', this)">
                            <div class="payment-method-logo" style="background:#fef2f2;">
                                <svg width="32" height="22" viewBox="0 0 32 22">
                                    <circle cx="11" cy="11" r="9" fill="#ef4444" fill-opacity="0.9"/>
                                    <circle cx="21" cy="11" r="9" fill="#f59e0b" fill-opacity="0.9"/>
                                </svg>
                            </div>
                            <span class="payment-method-name">Mastercard</span>
                        </div>

                        <!-- 6. Cash -->
                        <div class="payment-method-item" data-method="Cash" onclick="selectPaymentMethod('Cash', this)">
                            <div class="payment-method-logo" style="background:#ecfdf5;color:#047857;">
                                <svg width="28" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                                    <circle cx="12" cy="12" r="2"></circle>
                                    <path d="M6 12h.01M18 12h.01"></path>
                                </svg>
                            </div>
                            <span class="payment-method-name">Cash</span>
                        </div>

                    </div>
                </div>

                <!-- Card 2: Payment Details (Adaptive Form) -->
                <div class="checkout-card">
                    <h2 class="checkout-section-title" id="detailsSectionTitle">Payment Details</h2>

                    <!-- Cash Payment Fields -->
                    <div id="cashFields" style="display:none;">
                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;">
                            <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:#166534;margin-bottom:4px;font-size:0.92rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                                Pay with Cash
                            </div>
                            <p style="font-size:0.83rem;color:#15803d;margin:0;line-height:1.45;">
                                Pay cash directly at the stadium ticketing counter or match box office. Confirming records your payment method as Cash.
                            </p>
                        </div>
                        <div class="payment-field-group">
                            <label class="payment-label">Spectator Name</label>
                            <input type="text" class="payment-input" value="<?= htmlspecialchars($spectatorName) ?>" readonly style="background:#f8fafc;color:#334155;">
                        </div>
                        <div class="payment-field-group">
                            <label class="payment-label" for="cashPhone">Contact Phone Number</label>
                            <input type="text" id="cashPhone" name="cash_phone" class="payment-input" placeholder="01XXXXXXXXX" maxlength="15">
                        </div>
                        <p style="font-size:0.75rem;color:#64748b;margin-top:8px;">
                            Please present your Ticket ID at the stadium counter when entering or collecting physical ticket.
                        </p>
                    </div>

                    <!-- Card / SSLCommerz Fields -->
                    <div id="cardFields">
                        <div class="payment-field-group">
                            <label class="payment-label" for="cardholderName">Cardholder Name</label>
                            <input type="text" id="cardholderName" name="cardholder_name" class="payment-input"
                                   placeholder="Daud Ibrahim" value="<?= htmlspecialchars($spectatorName) ?>" required>
                        </div>

                        <div class="payment-field-group">
                            <label class="payment-label" for="cardNumber">Card Number</label>
                            <div class="payment-input-wrap">
                                <input type="text" id="cardNumber" name="card_number" class="payment-input"
                                       placeholder="0000 0000 0000 0000" maxlength="19" required>
                                <span class="payment-input-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                        <line x1="1" y1="10" x2="23" y2="10"></line>
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <div class="payment-grid-2">
                            <div class="payment-field-group">
                                <label class="payment-label" for="expiryDate">Expiry Date</label>
                                <input type="text" id="expiryDate" name="expiry_date" class="payment-input"
                                       placeholder="MM / YY" maxlength="7" required>
                            </div>
                            <div class="payment-field-group">
                                <label class="payment-label" for="cvv">CVV / CVC</label>
                                <input type="password" id="cvv" name="cvv" class="payment-input"
                                       placeholder="***" maxlength="4" required>
                            </div>
                        </div>

                        <label class="save-card-check">
                            <input type="checkbox" name="save_card" value="1" checked>
                            <span>Save this card for future transactions</span>
                        </label>
                    </div>

                    <!-- MFS (bKash / Nagad) Fields -->
                    <div id="mfsFields" style="display:none;">
                        <div class="payment-field-group">
                            <label class="payment-label" id="mfsNumberLabel">Mobile Account Number</label>
                            <div class="payment-input-wrap">
                                <input type="text" id="mfsNumber" name="mfs_number" class="payment-input"
                                       placeholder="017XXXXXXXX" maxlength="11">
                                <span class="payment-input-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                                        <line x1="12" y1="18" x2="12.01" y2="18"></line>
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <div class="payment-field-group">
                            <label class="payment-label">Account PIN</label>
                            <input type="password" id="mfsPin" name="mfs_pin" class="payment-input"
                                   placeholder="*****" maxlength="5">
                        </div>
                        <p style="font-size:0.75rem;color:#64748b;margin-top:8px;">
                            An OTP verification code will be sent to your mobile device upon clicking Confirm Payment.
                        </p>
                    </div>

                </div>
            </form>
        </div>

        <!-- Right Column: Booking Summary + Help Card -->
        <div class="checkout-right">

            <!-- Card 1: Booking Summary -->
            <div class="checkout-card">
                <div class="summary-header">
                    <span class="summary-title">Booking Summary</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2">
                        <circle cx="6" cy="6" r="3"></circle>
                        <circle cx="6" cy="18" r="3"></circle>
                        <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                        <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                        <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                    </svg>
                </div>

                <div class="summary-section">
                    <div class="summary-meta-label">TOURNAMENT</div>
                    <div class="summary-tournament-title"><?= htmlspecialchars($tournamentName) ?></div>
                </div>

                <div class="summary-details-row">
                    <div>
                        <div class="summary-meta-label">CATEGORY</div>
                        <div class="summary-detail-val"><?= htmlspecialchars($categoryName) ?></div>
                    </div>
                    <div>
                        <div class="summary-meta-label">TICKETS</div>
                        <div class="summary-detail-val"><?= htmlspecialchars($ticketsCount) ?></div>
                    </div>
                </div>

                <div class="summary-section">
                    <div class="summary-meta-label">DATE & TIME</div>
                    <div class="summary-detail-val"><?= $matchDateStr ?> | <?= $matchTimeStr ?></div>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-line-item">
                    <span>Subtotal</span>
                    <span>৳ <?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="summary-line-item">
                    <span>Service Fee (2.5%)</span>
                    <span>৳ <?= number_format($serviceFee, 2) ?></span>
                </div>
                <div class="summary-line-item">
                    <span>VAT (5%)</span>
                    <span>৳ <?= number_format($vat, 2) ?></span>
                </div>

                <div class="total-amount-container">
                    <span class="total-amount-label">Total Amount</span>
                    <span class="total-amount-val">
                        ৳ <?= number_format($totalAmount, 2) ?>
                    </span>
                </div>

                <button type="button" id="submitPaymentBtn" class="btn-confirm-payment">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    Confirm Payment
                </button>

                <div class="payment-terms-notice">
                    By clicking "Confirm Payment", you agree to our 
                    <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
                </div>
            </div>

            <!-- Card 2: Need Help With Payment? -->
            <div class="checkout-help-card">
                <div class="help-icon-wrap">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                    </svg>
                </div>
                <div>
                    <div class="help-text-title">Need help with payment?</div>
                    <div class="help-text-desc">Call 16123 or chat with support.</div>
                </div>
            </div>

        </div>

    </div>
    <?php endif; ?>

    <!-- Bottom Footer matching Figma -->
    <footer class="checkout-footer">
        <div style="display:flex;align-items:center;gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            <span>PCI DSS COMPLIANT &amp; 256-BIT SSL ENCRYPTED</span>
        </div>
        <div>
            &copy; 2026 STMS Admin. All rights reserved.
        </div>
    </footer>

    <script>
    function selectPaymentMethod(method, el) {
        // Toggle active class on cards
        document.querySelectorAll('.payment-method-item').forEach(i => i.classList.remove('active'));
        if (el) {
            el.classList.add('active');
        } else {
            const matched = document.querySelector(`.payment-method-item[data-method="${method}"]`);
            if (matched) matched.classList.add('active');
        }

        const selectedMethodInp = document.getElementById('selectedPayMethod');
        if (selectedMethodInp) selectedMethodInp.value = method;

        const cardFields   = document.getElementById('cardFields');
        const mfsFields    = document.getElementById('mfsFields');
        const cashFields   = document.getElementById('cashFields');
        const sectionTitle = document.getElementById('detailsSectionTitle');
        const mfsLabel     = document.getElementById('mfsNumberLabel');
        const submitBtn    = document.getElementById('submitPaymentBtn');

        const cardNumInput  = document.getElementById('cardNumber');
        const expiryInput   = document.getElementById('expiryDate');
        const cvvInput      = document.getElementById('cvv');
        const cardNameInput = document.getElementById('cardholderName');
        const mfsNumInput   = document.getElementById('mfsNumber');
        const mfsPinInput   = document.getElementById('mfsPin');

        if (method === 'Cash') {
            if (cardFields) cardFields.style.display = 'none';
            if (mfsFields)  mfsFields.style.display  = 'none';
            if (cashFields) cashFields.style.display = 'block';

            if (sectionTitle) sectionTitle.textContent = 'Cash Payment Details';

            // Disable card inputs and MFS inputs so browser never requires them
            [cardNumInput, expiryInput, cvvInput, cardNameInput, mfsNumInput, mfsPinInput].forEach(inp => {
                if (inp) {
                    inp.removeAttribute('required');
                    inp.disabled = true;
                }
            });

            if (submitBtn) {
                submitBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                        <circle cx="12" cy="12" r="2"></circle>
                        <path d="M6 12h.01M18 12h.01"></path>
                    </svg>
                    Confirm Cash Payment
                `;
            }
        } else if (method === 'bKash' || method === 'Nagad') {
            if (cardFields) cardFields.style.display = 'none';
            if (cashFields) cashFields.style.display = 'none';
            if (mfsFields)  mfsFields.style.display  = 'block';

            if (sectionTitle) sectionTitle.textContent = `${method} Payment Details`;
            if (mfsLabel)     mfsLabel.textContent     = `${method} Mobile Number`;

            // Disable card inputs
            [cardNumInput, expiryInput, cvvInput, cardNameInput].forEach(inp => {
                if (inp) {
                    inp.removeAttribute('required');
                    inp.disabled = true;
                }
            });

            // Enable MFS inputs
            if (mfsNumInput) { mfsNumInput.disabled = false; mfsNumInput.setAttribute('required', 'required'); }
            if (mfsPinInput) { mfsPinInput.disabled = false; mfsPinInput.setAttribute('required', 'required'); }

            if (submitBtn) {
                submitBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    Pay with ${method}
                `;
            }
        } else {
            // Card / SSLCommerz
            if (cardFields) cardFields.style.display = 'block';
            if (mfsFields)  mfsFields.style.display  = 'none';
            if (cashFields) cashFields.style.display = 'none';

            if (sectionTitle) sectionTitle.textContent = 'Payment Details';

            // Enable card inputs
            [cardNumInput, expiryInput, cvvInput, cardNameInput].forEach(inp => {
                if (inp) {
                    inp.disabled = false;
                    inp.setAttribute('required', 'required');
                }
            });

            // Disable MFS inputs
            [mfsNumInput, mfsPinInput].forEach(inp => {
                if (inp) {
                    inp.removeAttribute('required');
                    inp.disabled = true;
                }
            });

            if (submitBtn) {
                submitBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    Confirm Payment
                `;
            }
        }
    }
    </script>
    <script src="<?= BASE_URL ?>js/payment.js?v=<?= time() ?>"></script>
</body>
</html>
