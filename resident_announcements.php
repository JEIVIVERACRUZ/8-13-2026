<?php
// ============================================================
// resident_announcements.php  — Resident: View announcements
// ============================================================
session_start();
require_once 'includes/db.php';
requireResidentLogin();

$announcements = dbFetchAll(
    "SELECT * FROM announcements WHERE is_active=1 ORDER BY publish_date DESC, created_at DESC"
);

$colorMap = [
    'primary' => ['bg'=>'var(--primary-light)','border'=>'var(--primary)','text'=>'var(--primary)'],
    'success' => ['bg'=>'#dcfce7','border'=>'#22c55e','text'=>'#166534'],
    'warning' => ['bg'=>'#fef3c7','border'=>'#f59e0b','text'=>'#92400e'],
    'danger'  => ['bg'=>'#fee2e2','border'=>'#ef4444','text'=>'#991b1b'],
    'info'    => ['bg'=>'#dbeafe','border'=>'#3b82f6','text'=>'#1e40af'],
];

$categoryIcon = [
    'Event'          => '📣',
    'Service Update' => '📢',
    'Advisory'       => '⚠️',
    'Important Notice'=> '🔔',
    'Other'          => '📌',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Announcements — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_resident.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Barangay Announcements</h3>
        <div class="text-muted">Stay updated with the latest news from Barangay San Isidro</div>
      </div>
    </div>

    <div class="card">
      <h3>All Announcements</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-top:16px">
        <?php if ($announcements): foreach ($announcements as $a):
          $c  = $colorMap[$a['color']] ?? $colorMap['primary'];
          $dt = $a['publish_date'] ? date('M d, Y', strtotime($a['publish_date'])) : date('M d, Y', strtotime($a['created_at']));
        ?>
        <div style="background:<?= $c['bg'] ?>;border-left:4px solid <?= $c['border'] ?>;padding:16px;border-radius:8px;cursor:default;transition:all 0.3s"
             onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
             onmouseout="this.style.boxShadow='none'">
          <h4 style="margin:0 0 8px;color:<?= $c['text'] ?>"><?= htmlspecialchars($a['title']) ?></h4>
          <p style="margin:0;font-size:13px;color:var(--text-light)"><?= htmlspecialchars($a['description']) ?></p>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid rgba(0,0,0,0.1)">
            <small style="color:var(--text-light)"><strong>Category:</strong> <?= htmlspecialchars($a['category']) ?></small>
            <small style="color:var(--text-light)"><strong>Date:</strong> <?= $dt ?></small>
          </div>
        </div>
        <?php endforeach; else: ?>
        <p class="text-muted" style="grid-column:1/-1">No announcements at this time. Check back soon.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Activity Feed -->
    <div class="card">
      <h3>Recent Activity Feed</h3>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php if ($announcements): foreach (array_slice($announcements, 0, 5) as $a):
          $icon = $categoryIcon[$a['category']] ?? '📌';
          $dt = $a['publish_date'] ? date('M d, Y', strtotime($a['publish_date'])) : date('M d, Y', strtotime($a['created_at']));
        ?>
        <div style="display:flex;gap:16px;padding:12px;background:var(--muted);border-radius:8px">
          <div style="font-size:24px;min-width:32px"><?= $icon ?></div>
          <div style="flex:1">
            <h4 style="margin:0 0 4px;font-size:14px"><?= htmlspecialchars($a['title']) ?></h4>
            <p style="margin:0;font-size:12px;color:var(--text-light)"><?= htmlspecialchars(mb_substr($a['description'],0,80)) ?>... — <?= $dt ?></p>
          </div>
        </div>
        <?php endforeach; else: ?>
        <p class="text-muted">No recent activity.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
