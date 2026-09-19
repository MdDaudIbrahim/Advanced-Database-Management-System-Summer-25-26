<?php
// views/customer/home.php
// Figma: "Home - STMS Customer Portal.png"

session_start();
define('BASE_URL', '/ALL CODES/ADMS STMS/');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header('Location: ' . BASE_URL . 'views/auth/login.php'); exit();
}
require_once __DIR__ . '/../../config/db.php';
$conn = getOracleConnection();

// ── Featured tournament (latest active or upcoming) ──
$featured = oracleQuery($conn, "
    SELECT T_NAME, SPORT_TYPE, LOCATION, STARTDATE, ENDDATE
    FROM TOURNAMENT
    WHERE ENDDATE >= TRUNC(SYSDATE)
    ORDER BY STARTDATE ASC
    FETCH FIRST 1 ROW ONLY
")[0] ?? null;

// ── Upcoming Matches ──────────────────────────────
$upcomingMatches = oracleQuery($conn, "
    SELECT M.MATCHID, M.MATCHDATE, M.MATCHTIME,
           HT.TEAMNAME AS HOME_TEAM, AT.TEAMNAME AS AWAY_TEAM,
           V.V_NAME AS VENUE, T.T_NAME AS TOURNAMENT,
           V.CAPACITY,
           (SELECT COUNT(*) FROM TICKET TK WHERE TK.MATCHID=M.MATCHID) AS TICKETS_SOLD
    FROM MATCHES M
    JOIN TEAM HT ON M.HOMETEAMID=HT.TEAMID
    JOIN TEAM AT ON M.AWAYTEAMID=AT.TEAMID
    JOIN VENUE V  ON M.VENUEID=V.VENUEID
    JOIN TOURNAMENT T ON M.TOURNAMENTID=T.TOURNAMENTID
    WHERE M.MATCHSTATUS IN ('Scheduled', 'Confirmed')
    ORDER BY M.MATCHDATE ASC
    FETCH FIRST 4 ROWS ONLY
");

// ── My tickets/stats ──────────────────────────────
$myId = intval($_SESSION['user_id'] ?? 0);
$myTickets = $myId ? (oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TICKET WHERE SPECTATORID=:sid", ['sid'=>$myId])[0]['CNT'] ?? 0) : 0;
$myPending = $myId ? (oracleQuery($conn, "SELECT COUNT(*) AS CNT FROM TICKET WHERE SPECTATORID=:sid AND PAYMENT_STATUS='Pending'", ['sid'=>$myId])[0]['CNT'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home — STMS Customer Portal</title>
    <meta name="description" content="Watch university sports tournaments — browse upcoming matches, teams and buy tickets.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --green: #1b5e20;
            --green-dark: #145016;
            --green-light: #e8f5e9;
            --border: #e2e8f0;
            --text: #1a202c;
            --muted: #718096;
            --bg: #ffffff;
            --bg-main: #f8fafc;
            --radius: 8px;
            --radius-lg: 12px;
            --shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
        a { text-decoration: none; color: inherit; }

        /* ── Navbar ── */
        .cust-nav {
            position: sticky; top: 0; z-index: 100;
            background: #fff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 40px;
            height: 60px;
            gap: 24px;
        }
        .cust-nav-brand {
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--green);
            letter-spacing: -0.02em;
        }
        .cust-nav-search {
            flex: 1;
            max-width: 320px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 8px 14px;
        }
        .cust-nav-search input {
            border: none; outline: none; background: transparent;
            font-family: inherit; font-size: 0.85rem; color: var(--text); width: 100%;
        }
        .cust-nav-links {
            display: flex; align-items: center; gap: 28px; margin-left: auto;
        }
        .cust-nav-link {
            font-size: 0.9rem; font-weight: 500; color: var(--muted); padding: 4px 0;
            border-bottom: 2px solid transparent; transition: all 0.2s;
        }
        .cust-nav-link.active,
        .cust-nav-link:hover { color: var(--green); border-bottom-color: var(--green); }
        .cust-nav-icons { display: flex; align-items: center; gap: 12px; margin-left: 20px; }
        .cust-nav-icon {
            width: 34px; height: 34px; border: 1px solid var(--border); border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer;
        }
        .cust-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--green); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.78rem; cursor: pointer;
        }

        /* ── Main 2-Column Layout ── */
        .home-layout {
            display: grid;
            grid-template-columns: 1fr 310px;
            gap: 24px;
            padding: 24px 40px 48px;
            align-items: start;
        }
        .home-main {
            display: flex;
            flex-direction: column;
            gap: 24px;
            min-width: 0;
        }
        .home-sidebar {
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-width: 0;
        }

        /* ── Hero Banner ── */
        .hero-banner {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            height: 340px;
            background: #0f3714;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        .hero-banner-img {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            object-position: center 35%;
        }
        .hero-banner-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.72) 0%, rgba(0, 0, 0, 0.35) 45%, rgba(0, 0, 0, 0.05) 100%),
                        linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 60%);
        }
        .hero-banner-content {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 32px 36px;
            color: #fff;
        }
        .hero-featured-tag {
            display: inline-block;
            background: var(--green);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            padding: 5px 12px;
            border-radius: 4px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .hero-title {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 10px;
            text-shadow: 0 2px 6px rgba(0,0,0,0.35);
        }
        .hero-desc {
            font-size: 0.92rem;
            opacity: 0.9;
            margin-bottom: 20px;
            max-width: 520px;
            line-height: 1.5;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .hero-actions { display: flex; gap: 14px; }
        .btn-hero-primary {
            background: var(--green); color: #fff;
            padding: 11px 24px; border-radius: 6px;
            font-weight: 700; font-size: 0.85rem; letter-spacing: 0.04em;
            text-transform: uppercase; transition: background 0.2s, transform 0.15s;
            border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-hero-primary:hover { background: var(--green-dark); transform: translateY(-1px); }
        .btn-hero-outline {
            background: rgba(255,255,255,0.2); color: #fff;
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            padding: 11px 24px; border-radius: 6px;
            font-weight: 700; font-size: 0.85rem; letter-spacing: 0.04em;
            border: 1px solid rgba(255,255,255,0.45);
            cursor: pointer; transition: background 0.2s, transform 0.15s;
            display: inline-flex; align-items: center; gap: 6px;
            text-transform: uppercase;
        }
        .btn-hero-outline:hover { background: rgba(255,255,255,0.3); transform: translateY(-1px); }

        /* ── Right Sidebar ── */
        .announcements-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .announce-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 8px;
            font-weight: 700; font-size: 0.95rem;
        }
        .announce-item {
            padding: 12px 18px;
            border-bottom: 1px solid var(--border);
        }
        .announce-item:last-of-type { border-bottom: none; }
        .announce-date { font-size: 0.72rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
        .announce-title { font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; }
        .announce-desc { font-size: 0.78rem; color: var(--muted); line-height: 1.4; }
        .announce-archive {
            display: block; text-align: center; padding: 12px;
            font-size: 0.82rem; color: var(--muted); border-top: 1px solid var(--border);
            transition: color 0.2s;
        }
        .announce-archive:hover { color: var(--green); }

        .quick-stats-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            margin-top: 16px;
        }
        .quick-stats-title {
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--muted); margin-bottom: 12px;
        }
        .quick-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .quick-stat-box {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 12px;
            text-align: center;
        }
        .qs-val { font-size: 1.4rem; font-weight: 800; color: var(--green); }
        .qs-label { font-size: 0.72rem; color: var(--muted); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.04em; }

        /* ── Match Cards Section ── */
        .matches-section {
            display: flex;
            flex-direction: column;
        }
        .section-heading {
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .section-sub {
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 16px;
        }
        .view-all-link {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--green);
        }
        .view-all-link:hover { text-decoration: underline; }
        .match-cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .match-card-c {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 22px;
            background: #fff;
            transition: box-shadow 0.2s, transform 0.15s;
        }
        .match-card-c:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .match-card-tag {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .selling-fast-tag {
            background: #fee2e2; color: #dc2626;
            font-size: 0.68rem; font-weight: 700;
            padding: 3px 8px; border-radius: 4px; letter-spacing: 0.04em;
        }
        .avail-tag {
            background: #e2e8f0; color: #475569;
            font-size: 0.68rem; font-weight: 700;
            padding: 3px 8px; border-radius: 4px; letter-spacing: 0.04em;
        }
        .match-card-teams {
            display: flex;
            align-items: center;
            justify-content: space-around;
            margin-bottom: 18px;
        }
        .match-team-side { text-align: center; flex: 1; }
        .team-badge-c {
            width: 46px; height: 46px;
            border: 1px solid var(--border);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem;
            color: var(--green);
            background: #f8fafc;
            margin: 0 auto 8px;
        }
        .team-name-c { font-size: 0.98rem; font-weight: 800; color: #0f172a; }
        .vs-text { font-size: 0.95rem; font-weight: 700; color: #94a3b8; padding: 0 10px; }
        .match-meta-row {
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 6px;
            display: flex; align-items: center; gap: 8px;
        }
        .match-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }
        .match-price { font-size: 1.35rem; font-weight: 800; color: #0f172a; }
        .match-price-label { font-size: 0.72rem; color: #64748b; text-transform: capitalize; margin-bottom: 1px; }
        .btn-buy {
            background: var(--green); color: #fff;
            padding: 10px 22px; border-radius: 6px;
            font-size: 0.82rem; font-weight: 700; letter-spacing: 0.04em;
            border: none; cursor: pointer; transition: background 0.2s;
            text-transform: uppercase;
        }
        .btn-buy:hover { background: var(--green-dark); }

        /* ── Footer ── */
        .cust-footer {
            background: #fff;
            border-top: 1px solid var(--border);
            padding: 48px 40px 24px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 32px;
            margin-bottom: 32px;
        }
        .footer-brand { font-weight: 800; font-size: 1rem; color: var(--green); margin-bottom: 10px; }
        .footer-brand-desc { font-size: 0.82rem; color: var(--muted); line-height: 1.6; }
        .footer-col-title {
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--text); margin-bottom: 14px;
        }
        .footer-link {
            display: block; font-size: 0.85rem; color: var(--muted);
            margin-bottom: 10px; transition: color 0.2s;
        }
        .footer-link:hover { color: var(--green); }
        .footer-bar {
            border-top: 1px solid var(--border);
            padding-top: 20px;
            font-size: 0.8rem;
            color: var(--muted);
        }
    </style>
</head>
<body>

<!-- Unified Navbar -->
<?php $currentPage = 'home'; include __DIR__ . '/../../includes/customer_navbar.php'; ?>

<!-- Home Main 2-Column Grid matching Figma -->
<div class="home-layout">
    <!-- Left Column: Hero Banner + Upcoming Matches -->
    <div class="home-main">
        <!-- Hero Banner -->
        <div class="hero-banner">
            <img src="<?= BASE_URL ?>assets/images/tournament-upl.jpg" class="hero-banner-img" alt="Featured Tournament">
            <div class="hero-banner-overlay"></div>
            <div class="hero-banner-content">
                <span class="hero-featured-tag">Featured Tournament</span>
                <h1 class="hero-title"><?= htmlspecialchars($featured['T_NAME'] ?? 'University Premier League 2024') ?></h1>
                <p class="hero-desc">
                    Experience the pinnacle of collegiate <?= htmlspecialchars($featured['SPORT_TYPE'] ?? 'cricket') ?>.
                    <?php if ($featured): ?>
                    <?= date('M d', strtotime($featured['STARTDATE'])) ?> – <?= date('M d, Y', strtotime($featured['ENDDATE'])) ?>.
                    Tickets are now live.
                    <?php else: ?>
                    16 teams, 4 weeks, one champion. Tickets for the Grand Finale are now live.
                    <?php endif; ?>
                </p>
                <div class="hero-actions">
                    <a href="<?= BASE_URL ?>views/customer/tournaments.php" class="btn-hero-primary">View Tournament</a>
                    <a href="<?= BASE_URL ?>views/customer/book_ticket.php" class="btn-hero-outline">Full Schedule</a>
                </div>
            </div>
        </div>

        <!-- Upcoming Matches Section -->
        <div class="matches-section">
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:4px;">
                <h2 class="section-heading">Upcoming Matches</h2>
                <a href="<?= BASE_URL ?>views/customer/tournaments.php" class="view-all-link">View All Matches →</a>
            </div>
            <p class="section-sub">Don't miss the action. Secure your spot in the stands.</p>

            <div class="match-cards-grid">
                <?php if (empty($upcomingMatches)): ?>
                <div style="grid-column:span 2;padding:32px;text-align:center;color:var(--muted);border:1px solid var(--border);border-radius:var(--radius-lg);">
                    No upcoming matches scheduled. Check back soon!
                </div>
                <?php else: ?>
                <?php foreach ($upcomingMatches as $idx => $m):
                    $seatsLeft = max(0, ($m['CAPACITY'] ?? 500) - ($m['TICKETS_SOLD'] ?? 0));
                    $isPopular = $seatsLeft < 200;
                    $price = 200; // default BDT
                ?>
                <div class="match-card-c">
                    <div class="match-card-tag">
                        <span><?= htmlspecialchars($m['TOURNAMENT']) ?></span>
                        <?php if ($isPopular): ?>
                        <span class="selling-fast-tag">SELLING FAST</span>
                        <?php else: ?>
                        <span class="avail-tag">AVAILABLE</span>
                        <?php endif; ?>
                    </div>
                    <div class="match-card-teams">
                        <div class="match-team-side">
                            <div class="team-badge-c">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <div class="team-name-c"><?= htmlspecialchars($m['HOME_TEAM']) ?></div>
                        </div>
                        <span class="vs-text">VS</span>
                        <div class="match-team-side">
                            <div class="team-badge-c">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            </div>
                            <div class="team-name-c"><?= htmlspecialchars($m['AWAY_TEAM']) ?></div>
                        </div>
                    </div>
                    <div class="match-meta-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?= htmlspecialchars($m['VENUE']) ?>
                    </div>
                    <div class="match-meta-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?= date('M d, Y', strtotime($m['MATCHDATE'])) ?> &bull; <?= htmlspecialchars($m['MATCHTIME']) ?>
                    </div>
                    <div class="match-meta-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <?= number_format($seatsLeft) ?> Seats Available
                    </div>
                    <div class="match-footer">
                        <div>
                            <div class="match-price-label">Starting from</div>
                            <div class="match-price">৳<?= $price ?></div>
                        </div>
                        <a href="<?= BASE_URL ?>views/customer/book_ticket.php?match_id=<?= $m['MATCHID'] ?>" class="btn-buy">BUY TICKET</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Announcements & Quick Stats Sidebar -->
    <aside class="home-sidebar">
        <div class="announcements-card">
            <div class="announce-header">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Announcements
            </div>
            <div class="announce-item">
                <div class="announce-date">Oct 20, 2023</div>
                <div class="announce-title">Stadium gate policy update for UPL Finale</div>
                <div class="announce-desc">Please note that gates will open 3 hours prior to match start....</div>
            </div>
            <div class="announce-item">
                <div class="announce-date">Oct 18, 2023</div>
                <div class="announce-title">Refund policy for rained-out matches</div>
                <div class="announce-desc">In case of cancellation due to weather, full refunds will be...</div>
            </div>
            <div class="announce-item">
                <div class="announce-date">Oct 15, 2023</div>
                <div class="announce-title">New Hospitality Boxes available</div>
                <div class="announce-desc">Experience luxury with our new private boxes. Includes catering and...</div>
            </div>
            <a href="#" class="announce-archive">Archive</a>
        </div>

        <div class="quick-stats-card">
            <div class="quick-stats-title">My Quick Stats</div>
            <div class="quick-stats-grid">
                <a href="<?= BASE_URL ?>views/customer/my_tickets.php" class="quick-stat-box" style="text-decoration:none;transition:all 0.2s;" title="View all my booked tickets">
                    <div class="qs-val"><?= str_pad($myTickets, 2, '0', STR_PAD_LEFT) ?></div>
                    <div class="qs-label">My Tickets</div>
                </a>
                <a href="<?= BASE_URL ?>views/customer/my_tickets.php" class="quick-stat-box" style="text-decoration:none;transition:all 0.2s;" title="View tickets pending payment approval">
                    <div class="qs-val" style="color:<?= $myPending > 0 ? '#d97706' : '#166534' ?>;"><?= str_pad($myPending, 2, '0', STR_PAD_LEFT) ?></div>
                    <div class="qs-label">Pending Pay</div>
                </a>
            </div>
        </div>
    </aside>
</div>

<!-- Footer -->
<footer class="cust-footer">
    <div class="footer-grid">
        <div>
            <div class="footer-brand">STMS</div>
            <p class="footer-brand-desc">Professional grade tournament management for modern sports organizations. Reliable, fast, and transparent.</p>
        </div>
        <div>
            <div class="footer-col-title">Platform</div>
            <a href="<?= BASE_URL ?>views/customer/tournaments.php" class="footer-link">Tournaments</a>
            <a href="<?= BASE_URL ?>views/customer/tournaments.php" class="footer-link">Teams &amp; Players</a>
            <a href="<?= BASE_URL ?>views/customer/book_ticket.php" class="footer-link">Schedules</a>
        </div>
        <div>
            <div class="footer-col-title">Ticketing</div>
            <a href="<?= BASE_URL ?>views/customer/my_tickets.php" class="footer-link">My Tickets &amp; Payments</a>
            <a href="<?= BASE_URL ?>views/customer/book_ticket.php" class="footer-link">Book Passes</a>
            <a href="#" class="footer-link">Stadium Map</a>
        </div>
        <div>
            <div class="footer-col-title">Contact</div>
            <a href="mailto:support@stms.edu.bd" class="footer-link">support@stms.edu.bd</a>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <a href="#" class="footer-link" style="display:inline-flex;align-items:center;gap:4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </a>
                <a href="#" class="footer-link" style="display:inline-flex;align-items:center;gap:4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </a>
            </div>
        </div>
    </div>
    <div class="footer-bar">
        &copy; <?= date('Y') ?> Sports Tournament Management System. All rights reserved.
        <span style="float:right;">
            <a href="#" style="color:var(--muted);margin-left:16px;">Privacy Policy</a>
            <a href="#" style="color:var(--muted);margin-left:16px;">Terms of Service</a>
        </span>
    </div>
</footer>

<script>
// Navbar active state on scroll (minor UX)
window.addEventListener('scroll', () => {
    document.querySelector('.cust-nav').style.boxShadow =
        window.scrollY > 10 ? '0 2px 12px rgba(0,0,0,0.08)' : 'none';
});
</script>
</body>
</html>
