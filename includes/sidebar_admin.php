<?php
// includes/sidebar_admin.php  — reusable admin sidebar
// Requires site_settings.php to already be loaded (done via db.php chain).
$currentPage = basename($_SERVER['PHP_SELF']);
$adminName   = $_SESSION['admin_name'] ?? 'Admin';
$adminRole   = $_SESSION['admin_role'] ?? 'Staff';

$siteName = getSiteName();
$siteLogo = getSiteLogo();

$navItems = [
    ['href'=>'dashboard.php',    'icon'=>'🏠', 'label'=>'Dashboard'],
    ['href'=>'my_inhabitants.php',   'icon'=>'👥', 'label'=>'My Inhabitant Profile'],
    ['href'=>'issuance.php',     'icon'=>'🧾', 'label'=>'Issuance / Documents'],
    ['href'=>'document_templates.php','icon'=>'📋', 'label'=>'Document Templates'],
    ['href'=>'announcements.php','icon'=>'📢', 'label'=>'Announcements'],
    ['href'=>'households.php',   'icon'=>'🏠', 'label'=>'Households'],
    ['href'=>'appointments.php', 'icon'=>'📅', 'label'=>'Appointments'],
    ['href'=>'mapping.php',      'icon'=>'📍', 'label'=>'Geographic Mapping'],
    ['href'=>'reports.php',      'icon'=>'📊', 'label'=>'Reports Management'],
    ['href'=>'users.php',        'icon'=>'🔒', 'label'=>'User Management'],
    ['href'=>'settings.php',     'icon'=>'⚙️', 'label'=>'Settings'],
];
?>
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo-box">
      <img src="<?= $siteLogo ?>" alt="Logo">
    </div>
    <div>
      <h3>Barangay Admin</h3>
      <p class="text-muted"><?= $siteName ?></p>
    </div>
  </div>

  <!-- Logged-in admin info -->
  <div style="margin:0 4px 16px;padding:10px 14px;background:var(--primary-light);border-radius:10px;border-left:3px solid var(--primary)">
    <div style="font-size:13px;font-weight:600;color:var(--primary)"><?= htmlspecialchars($adminName) ?></div>
    <div style="font-size:11px;color:var(--text-light)"><?= htmlspecialchars($adminRole) ?></div>
  </div>

  <ul class="menu">
    <?php foreach ($navItems as $item): ?>
    <li>
      <a href="<?= $item['href'] ?>" class="<?= $currentPage === $item['href'] ? 'active' : '' ?>">
        <div class="icon"><?= $item['icon'] ?></div>
        <span><?= $item['label'] ?></span>
      </a>
    </li>
    <?php endforeach; ?>
    <li>
      <a href="logout.php" style="color:#ef4444">
        <div class="icon">🚪</div><span>Sign Out</span>
      </a>
    </li>
  </ul>
</aside>
