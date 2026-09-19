<?php
// includes/admin_sidebar.php
// Admin Portal Sidebar — matches Figma: Dashboard - Sports Tournament Management System

$currentPage = $currentPage ?? '';
define('BASE_URL_ADMIN', '/ALL CODES/ADMS STMS/');

$navItems = [
    ['href' => BASE_URL_ADMIN . 'views/admin/dashboard.php',         'label' => 'Dashboard',   'key' => 'dashboard',   'icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
    ['href' => BASE_URL_ADMIN . 'views/admin/tournaments.php',        'label' => 'Tournaments', 'key' => 'tournaments', 'icon' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>'],
    ['href' => BASE_URL_ADMIN . 'views/admin/schedule_match.php',     'label' => 'Matches',     'key' => 'matches',     'icon' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'],
    ['href' => BASE_URL_ADMIN . 'views/admin/team_registration.php',  'label' => 'Teams',       'key' => 'teams',       'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['href' => BASE_URL_ADMIN . 'views/admin/player_management.php',  'label' => 'Players',     'key' => 'players',     'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    ['href' => BASE_URL_ADMIN . 'views/admin/payment_recording.php',  'label' => 'Ticket Sales & Payments', 'key' => 'payments', 'icon' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>'],
];
?>
<aside class="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-name">STMS Admin</div>
        <div class="sidebar-brand-sub">University Sports</div>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <div class="sidebar-item">
            <a href="<?= $item['href'] ?>"
               class="sidebar-link <?= $currentPage === $item['key'] ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <?= $item['icon'] ?>
                </svg>
                <span class="sidebar-link-text"><?= $item['label'] ?></span>
            </a>
        </div>
        <?php endforeach; ?>
    </nav>

    <!-- User Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?= strtoupper(substr($_SESSION['name'] ?? 'AD', 0, 2)) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin User') ?></div>
                <div class="sidebar-user-role">Admin Access</div>
            </div>
            <a href="<?= BASE_URL_ADMIN ?>controllers/AuthController.php?action=logout"
               class="sidebar-logout" title="Logout">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </div>
</aside>
