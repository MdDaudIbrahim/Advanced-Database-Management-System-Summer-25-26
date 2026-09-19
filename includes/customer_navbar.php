<?php
// includes/customer_navbar.php — Unified Customer Portal Navbar
// Sports Tournament Management System (STMS)

if (!defined('BASE_URL')) define('BASE_URL', '/ALL CODES/ADMS STMS/');
$currentPage = $currentPage ?? '';
$customerName = $_SESSION['name'] ?? 'Customer';
$customerInitials = strtoupper(substr($customerName, 0, 2));
?>
<style>
.cust-nav {
    position: sticky; top: 0; z-index: 100;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    padding: 0 36px;
    height: 64px;
    gap: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.cust-nav-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 800;
    font-size: 1.15rem;
    color: #1b5e20;
    letter-spacing: -0.02em;
    text-decoration: none;
    margin-right: 8px;
}
.cust-nav-brand span.badge-pill {
    background: #e8f5e9;
    color: #1b5e20;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.cust-nav-links {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-left: 12px;
}
.cust-nav-link {
    font-size: 0.88rem;
    font-weight: 600;
    color: #64748b;
    padding: 6px 0;
    border-bottom: 2px solid transparent;
    text-decoration: none;
    transition: all 0.2s ease;
}
.cust-nav-link:hover {
    color: #1b5e20;
}
.cust-nav-link.active {
    color: #1b5e20;
    border-bottom-color: #1b5e20;
}
.cust-nav-right {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-left: auto;
}
.cust-user-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 10px 4px 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
}
.cust-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #1b5e20;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.75rem;
}
.cust-user-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: #1e293b;
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cust-nav-exit {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}
.cust-nav-exit:hover {
    background: #ef4444;
    color: #ffffff;
    border-color: #dc2626;
}
</style>

<nav class="cust-nav">
    <a href="<?= BASE_URL ?>views/customer/home.php" class="cust-nav-brand">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1b5e20" stroke-width="2.2">
            <circle cx="12" cy="12" r="10"></circle>
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
        </svg>
        STMS <span class="badge-pill">Customer</span>
    </a>

    <div class="cust-nav-links">
        <a href="<?= BASE_URL ?>views/customer/home.php" class="cust-nav-link <?= ($currentPage === 'home') ? 'active' : '' ?>">Home</a>
        <a href="<?= BASE_URL ?>views/customer/tournaments.php" class="cust-nav-link <?= ($currentPage === 'tournaments') ? 'active' : '' ?>">Tournaments</a>
        <a href="<?= BASE_URL ?>views/customer/book_ticket.php" class="cust-nav-link <?= ($currentPage === 'book_ticket') ? 'active' : '' ?>">Book Tickets</a>
        <a href="<?= BASE_URL ?>views/customer/my_tickets.php" class="cust-nav-link <?= ($currentPage === 'my_tickets') ? 'active' : '' ?>">My Bookings &amp; Payments</a>
    </div>

    <div class="cust-nav-right">
        <div class="cust-user-badge">
            <div class="cust-avatar"><?= $customerInitials ?></div>
            <span class="cust-user-name"><?= htmlspecialchars($customerName) ?></span>
        </div>
        <a href="<?= BASE_URL ?>controllers/AuthController.php?action=logout" class="cust-nav-exit" title="Exit / Logout from STMS">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Exit / Logout
        </a>
    </div>
</nav>
