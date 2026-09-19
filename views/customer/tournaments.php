<?php
// views/customer/tournaments.php
// Figma: "Tournaments - STMS Customer Portal.png"

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit();
}
require_once __DIR__ . '/../../config/db.php';
$conn = getOracleConnection();

// ── Pagination ────────────────────────────────────
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 6;
$offset  = ($page - 1) * $perPage;
$searchQ = trim($_GET['q'] ?? '');

$whereClause = '';
if ($searchQ) $whereClause = "WHERE UPPER(T_NAME) LIKE UPPER('%{$searchQ}%')";

$totalRows  = oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TOURNAMENT {$whereClause}")[0]['CNT'] ?? 0;
$totalPages = max(1, ceil($totalRows / $perPage));

$tournaments = oracleQuery($conn, "
    SELECT TOURNAMENTID, T_NAME, SPORT_TYPE, STARTDATE, ENDDATE,
           LOCATION, NVL(PRIZEMONEY, 0) AS PRIZE, MAX_TEAMS
    FROM TOURNAMENT
    {$whereClause}
    ORDER BY STARTDATE DESC
    OFFSET {$offset} ROWS FETCH NEXT {$perPage} ROWS ONLY
");

// Sport → Ticket price mapping
$sportPrice = ['Cricket'=>200,'Football'=>150,'Basketball'=>100,'Badminton'=>80,'Volleyball'=>80,'Table Tennis'=>60];

// Real Photography Asset Matcher based on Figma designs
function getTournamentImage($name, $sport = '') {
    $tLower = strtolower($name . ' ' . $sport);
    if (strpos($tLower, 'upl') !== false || strpos($tLower, 'aiub') !== false || strpos($tLower, 'university premier') !== false || strpos($tLower, 'collegiate') !== false) {
        return 'tournament-upl.jpg';
    } elseif (strpos($tLower, 'football') !== false || strpos($tLower, 'dept') !== false || strpos($tLower, 'soccer') !== false) {
        return 'tournament-football.jpg';
    } elseif (strpos($tLower, 'indoor') !== false || strpos($tLower, 'basketball') !== false || strpos($tLower, 'masters') !== false) {
        return 'tournament-indoor.jpg';
    } elseif (strpos($tLower, 'tennis') !== false) {
        return 'tournament-tennis.jpg';
    } elseif (strpos($tLower, 'corporate') !== false || strpos($tLower, 'badminton') !== false) {
        return 'tournament-corporate.jpg';
    } elseif (strpos($tLower, 'cricket') !== false || strpos($tLower, 't20') !== false) {
        return 'tournament-cricket.jpg';
    }
    return 'hero-stadium.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Tournaments — STMS</title>
    <meta name="description" content="Secure your seats for the most anticipated sports events across the country.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --green: #1b5e20; --green-dark: #145016; --green-light: #e8f5e9;
            --border: #e2e8f0; --text: #1a202c; --muted: #718096;
            --bg: #f8fafc; --bg-card: #fff;
            --radius: 8px; --radius-lg: 12px; --shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
        a { text-decoration: none; color: inherit; }

        /* Navbar */
        .cust-nav {
            background: #fff; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 40px; height: 60px; gap: 24px;
        }
        .cust-nav-brand { font-weight: 800; font-size: 1.1rem; color: var(--green); }
        .cust-nav-links { display: flex; gap: 28px; margin-left: auto; align-items: center; }
        .cust-nav-link { font-size: 0.9rem; font-weight: 500; color: var(--muted); padding: 4px 0; border-bottom: 2px solid transparent; }
        .cust-nav-link.active, .cust-nav-link:hover { color: var(--green); border-bottom-color: var(--green); }
        .nav-logout { margin-left: 24px; font-size: 0.85rem; color: var(--muted); display:flex;align-items:center;gap:6px; }
        .nav-logout:hover { color: var(--green); }

        /* Page header */
        .page-wrap { max-width: 1200px; margin: 0 auto; padding: 32px 40px; }
        .breadcrumb { font-size: 0.8rem; color: var(--muted); margin-bottom: 12px; }
        .breadcrumb a { color: var(--muted); }
        .breadcrumb span { color: var(--green); font-weight: 600; }
        .page-title { font-size: 2rem; font-weight: 800; margin-bottom: 6px; }
        .page-sub { font-size: 0.9rem; color: var(--muted); }
        .header-row {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 28px; gap: 20px;
        }
        .search-box-c {
            display: flex; align-items: center; gap: 8px;
            background: #fff; border: 1px solid var(--border);
            border-radius: var(--radius); padding: 10px 16px;
            min-width: 280px;
        }
        .search-box-c input { border: none; outline: none; background: transparent; font-family: inherit; font-size: 0.85rem; width: 100%; }

        /* Tournament Card Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }
        .tourn-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .tourn-card-img {
            height: 180px;
            position: relative;
            overflow: hidden;
            background: #0f172a;
        }
        .tourn-card-img-bg {
            width: 100%; height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.35s ease;
            display: block;
        }
        .tourn-card:hover .tourn-card-img-bg {
            transform: scale(1.05);
        }
        .ticket-status-badge {
            position: absolute; top: 12px; right: 12px;
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em;
            padding: 4px 10px; border-radius: 4px; text-transform: uppercase;
        }
        .ticket-open { background: var(--green); color: #fff; }
        .ticket-closed { background: #e2e3e5; color: #383d41; }
        .tourn-card-body { padding: 18px; }
        .tourn-name { font-size: 1.05rem; font-weight: 700; margin-bottom: 3px; }
        .tourn-season { font-size: 0.78rem; color: var(--muted); margin-bottom: 12px; }
        .tourn-meta-row {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.8rem; color: var(--muted);
            margin-bottom: 6px;
        }
        .tourn-prize {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.8rem; margin-bottom: 16px;
        }
        .prize-val { font-weight: 700; color: var(--green); }
        .tourn-card-footer {
            border-top: 1px solid var(--border);
            padding-top: 14px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .starts-from { font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px; }
        .price-from { font-size: 1.1rem; font-weight: 800; }
        .btn-buy-ticket {
            background: var(--green); color: #fff;
            padding: 9px 18px; border-radius: var(--radius);
            font-size: 0.82rem; font-weight: 700;
            display: flex; align-items: center; gap: 6px;
            transition: background 0.2s;
        }
        .btn-buy-ticket:hover { background: var(--green-dark); }
        .btn-upcoming {
            background: var(--bg); color: var(--muted);
            padding: 9px 18px; border-radius: var(--radius);
            font-size: 0.82rem; font-weight: 700; border: 1px solid var(--border);
        }

        /* Pagination */
        .pagination-wrap { display: flex; justify-content: center; align-items: center; gap: 6px; }
        .pag-btn {
            width: 38px; height: 38px; border: 1px solid var(--border);
            border-radius: var(--radius); background: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; font-weight: 500; color: var(--text);
            cursor: pointer; transition: all 0.2s;
        }
        .pag-btn:hover, .pag-btn.active { background: var(--green); border-color: var(--green); color: #fff; }

        /* Footer */
        .cust-footer {
            background: #fff; border-top: 1px solid var(--border);
            padding: 28px 40px; margin-top: 20px;
        }
        .footer-bar {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.8rem; color: var(--muted);
        }
        .footer-links { display: flex; gap: 20px; }
        .footer-links a { color: var(--muted); }
        .footer-links a:hover { color: var(--green); }
    </style>
</head>
<body>

<!-- Unified Navbar -->
<?php $currentPage = 'tournaments'; include __DIR__ . '/../../includes/customer_navbar.php'; ?>

<div class="page-wrap">
    <!-- Page Header -->
    <div class="breadcrumb">
        <a href="<?= BASE_URL ?>views/customer/home.php">Home</a> ›
        <span>Tournaments</span>
    </div>
    <div class="header-row">
        <div>
            <h1 class="page-title">Available Tournaments</h1>
            <p class="page-sub">Secure your seats for the most anticipated sports events across the country.</p>
        </div>
        <form method="GET" class="search-box-c">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#718096" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" placeholder="Search tournaments..." value="<?= htmlspecialchars($searchQ) ?>">
        </form>
    </div>

    <!-- Tournament Cards -->
    <div class="cards-grid">
        <?php if (empty($tournaments)): ?>
        <div style="grid-column:span 3;text-align:center;padding:60px;color:var(--muted);">
            No tournaments found<?= $searchQ ? ' for "' . htmlspecialchars($searchQ) . '"' : '' ?>.
        </div>
        <?php else: ?>
        <?php foreach ($tournaments as $t):
            $now   = time();
            $start = strtotime($t['STARTDATE']);
            $end   = strtotime($t['ENDDATE']);
            $isActive   = $now >= $start && $now <= $end;
            $isUpcoming = $now < $start;
            $isEnded    = $now > $end;
            $ticketOpen = !$isEnded;

            $sport    = htmlspecialchars($t['SPORT_TYPE'] ?? 'Sports');
            $gradient = $gradients[$t['SPORT_TYPE'] ?? ''] ?? 'linear-gradient(135deg,#1b5e20,#43a047)';
            $price    = $sportPrice[$t['SPORT_TYPE'] ?? ''] ?? 150;

            // Season from month
            $m = (int)date('n', $start);
            $season = ($m >= 11 || $m <= 2) ? 'Winter' : (($m >= 6 && $m <= 8) ? 'Summer' : 'Annual Championship');
        ?>
        <div class="tourn-card">
            <div class="tourn-card-img">
                <img src="<?= BASE_URL ?>assets/images/<?= getTournamentImage($t['T_NAME'], $t['SPORT_TYPE'] ?? '') ?>"
                     alt="<?= htmlspecialchars($t['T_NAME']) ?>"
                     class="tourn-card-img-bg"
                     loading="lazy">
                <span class="ticket-status-badge <?= $ticketOpen ? 'ticket-open' : 'ticket-closed' ?>">
                    <?= $ticketOpen ? 'TICKET OPEN' : 'CLOSED' ?>
                </span>
            </div>
            <div class="tourn-card-body">
                <div class="tourn-name"><?= htmlspecialchars($t['T_NAME']) ?></div>
                <div class="tourn-season">Season: <?= $season ?></div>
                <div class="tourn-meta-row">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?= date('M d', $start) ?> - <?= date('M d, Y', $end) ?>
                </div>
                <div class="tourn-meta-row">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= htmlspecialchars($t['LOCATION'] ?? 'University Campus') ?>
                </div>
                <div class="tourn-prize">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#1b5e20" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Prize: <span class="prize-val">৳<?= number_format($t['PRIZE']) ?></span>
                </div>

                <div class="tourn-card-footer">
                    <?php if ($ticketOpen): ?>
                    <div>
                        <div class="starts-from">Starts from</div>
                        <div class="price-from">৳<?= $price ?></div>
                    </div>
                    <a href="<?= BASE_URL ?>views/customer/book_ticket.php?tournament_id=<?= $t['TOURNAMENTID'] ?>" class="btn-buy-ticket">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Buy Ticket
                    </a>
                    <?php else: ?>
                    <span style="font-size:0.78rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">Not Available</span>
                    <span class="btn-upcoming">Upcoming</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap" style="margin-bottom:40px;">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($searchQ) ?>" class="pag-btn">‹</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 1); $p <= min($totalPages, $page + 2); $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($searchQ) ?>" class="pag-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($searchQ) ?>" class="pag-btn">›</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<footer class="cust-footer">
    <div style="font-weight:800;font-size:1rem;color:var(--green);margin-bottom:6px;">STMS</div>
    <div class="footer-bar">
        <span>&copy; <?= date('Y') ?> STMS Sports Tournament Management System. All rights reserved.</span>
        <div class="footer-links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Support</a>
            <a href="#">Contact Us</a>
        </div>
    </div>
</footer>

</body>
</html>
