<?php
// includes/sidebar_resident.php  — reusable resident sidebar
$currentPage  = basename($_SERVER['PHP_SELF']);
$residentName = $_SESSION['resident_name'] ?? 'Resident';

$siteName = getSiteName();
$siteLogo = getSiteLogo();

$navItems = [
    ['href'=>'resident_portal.php',       'icon'=>'🏠', 'label'=>'Home'],
    ['href'=>'resident_announcements.php','icon'=>'📢', 'label'=>'Announcements'],
    ['href'=>'resident_myprofile.php',      'icon'=>'👤', 'label'=>'My Profile'],
    ['href'=>'resident_documents.php',    'icon'=>'🧾', 'label'=>'My Documents'],
    ['href'=>'resident_appointments.php', 'icon'=>'📅', 'label'=>'Appointments'],
];
?>
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo-box">
      <img src="<?= $siteLogo ?>" alt="Logo">
    </div>
    <div>
      <h3>Barangay Portal</h3>
      <p class="text-muted"><?= $siteName ?></p>
    </div>
  </div>

  <!-- Logged-in resident info -->
  <div style="margin:0 4px 16px;padding:10px 14px;background:var(--primary-light);border-radius:10px;border-left:3px solid var(--primary)">
    <div style="font-size:13px;font-weight:600;color:var(--primary)"><?= htmlspecialchars($residentName) ?></div>
    <div style="font-size:11px;color:var(--text-light)">Resident</div>
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
