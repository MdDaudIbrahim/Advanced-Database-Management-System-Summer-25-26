<?php
// includes/staff_sidebar.php
$currentPage = $currentPage ?? '';
if (!defined('BASE_URL')) define('BASE_URL', '/ALL CODES/ADMS STMS/');
$navItems = [
    ['href' => BASE_URL . 'views/staff/dashboard.php',      'label' => 'Dashboard',       'key' => 'dashboard',  'icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
    ['href' => BASE_URL . 'views/staff/player_records.php', 'label' => 'Player Records',  'key' => 'players',    'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    ['href' => BASE_URL . 'views/staff/coach_records.php',  'label' => 'Coach Records',   'key' => 'coaches',    'icon' => '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
    ['href' => BASE_URL . 'views/staff/ticket_payments.php','label' => 'Ticket Payments', 'key' => 'tickets',    'icon' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>'],
    ['href' => BASE_URL . 'views/staff/profile.php',        'label' => 'My Profile',      'key' => 'profile',    'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
];
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-name">Staff Portal</div>
        <div class="sidebar-brand-sub">STMS — Data Entry</div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <div class="sidebar-item">
            <a href="<?= $item['href'] ?>" class="sidebar-link <?= $currentPage === $item['key'] ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $item['icon'] ?></svg>
                <span class="sidebar-link-text"><?= $item['label'] ?></span>
            </a>
        </div>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'ST', 0, 2)) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Staff') ?></div>
                <div class="sidebar-user-role">Staff Access</div>
            </div>
            <a href="<?= BASE_URL ?>controllers/AuthController.php?action=logout" class="sidebar-logout">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </div>
</aside>
