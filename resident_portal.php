<?php
// ============================================================
// resident_portal.php  — Resident home dashboard
// ============================================================
session_start();
require_once 'includes/db.php';
requireResidentLogin();

$residentId = $_SESSION['resident_id'];

$resident = dbFetchOne(
    "SELECT *, CONCAT(first_name,' ',last_name) AS full_name FROM residents WHERE id=?",
    [$residentId]
);

// Active (pending + processing) requests
$activeCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM document_requests WHERE resident_id=? AND status IN ('Pending','Processing')",
    [$residentId]
)['c'] ?? 0);

// Documents ready
$readyCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM document_requests WHERE resident_id=? AND status='Ready for Pickup'",
    [$residentId]
)['c'] ?? 0);

// Announcements
$announcements = dbFetchAll(
    "SELECT * FROM announcements WHERE is_active=1 ORDER BY publish_date DESC LIMIT 3"
);

// Active requests for table
$activeReqs = dbFetchAll(
    "SELECT * FROM document_requests WHERE resident_id=? AND status IN ('Pending','Processing') ORDER BY created_at DESC LIMIT 5",
    [$residentId]
);

$colorMap = [
    'primary' => ['bg'=>'var(--primary-light)','border'=>'var(--primary)','text'=>'var(--primary)'],
    'success' => ['bg'=>'#dcfce7','border'=>'#22c55e','text'=>'#166534'],
    'warning' => ['bg'=>'#fef3c7','border'=>'#f59e0b','text'=>'#92400e'],
    'danger'  => ['bg'=>'#fee2e2','border'=>'#ef4444','text'=>'#991b1b'],
    'info'    => ['bg'=>'#dbeafe','border'=>'#3b82f6','text'=>'#1e40af'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Resident Portal — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_resident.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Welcome, <?= htmlspecialchars($resident['full_name'] ?? 'Resident') ?></h3>
        <div class="text-muted">Your resident dashboard and barangay services</div>
      </div>
      <div class="topbar-actions">
        <a href="resident_documents.php" class="btn btn-primary btn-sm">Request Service</a>
      </div>
    </div>

    <div class="stats">
      <div class="stat">
        <h4>Resident ID</h4>
        <p style="font-size:16px;letter-spacing:1px;font-family:monospace"><?= htmlspecialchars($resident['barangay_id'] ?? '') ?></p>
        <div class="stat-icon">🆔</div>
        <div class="stat-change positive"><?= htmlspecialchars($resident['status']) ?></div>
      </div>
      <div class="stat">
        <h4>Active Requests</h4>
        <p data-target="<?= $activeCount ?>">0</p>
        <div class="stat-icon">📋</div>
        <div class="stat-change positive">In progress</div>
      </div>
      <div class="stat">
        <h4>Documents Ready</h4>
        <p data-target="<?= $readyCount ?>">0</p>
        <div class="stat-icon">✅</div>
        <div class="stat-change positive">Ready for pickup</div>
      </div>
    </div>

    <div class="hero">
      <div class="overlay">
        <h1>Quick Access to Services</h1>
        <p>Manage your residency, request documents, and stay updated with barangay announcements.</p>
      </div>
    </div>

    <div class="content-cards">
      <div class="card quick-action-card" onclick="location.href='resident_documents.php'">
        <h4>Request Document</h4>
        <small class="text-muted">Barangay clearance, certificates, IDs</small>
      </div>
      <div class="card quick-action-card" onclick="location.href='resident_appointments.php'">
        <h4>Book Appointment</h4>
        <small class="text-muted">Schedule office visits</small>
      </div>
      <div class="card quick-action-card" onclick="location.href='resident_announcements.php'">
        <h4>Announcements</h4>
        <small class="text-muted">Latest barangay news</small>
      </div>
      <div class="card quick-action-card" onclick="location.href='resident_myprofile.php'">
        <h4>My Profile</h4>
        <small class="text-muted">View and update your info</small>
      </div>
    </div>

    <!-- Active Requests -->
    <div class="card">
      <h3>My Active Requests</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Request ID</th>
              <th>Type</th>
              <th>Date Requested</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($activeReqs): foreach ($activeReqs as $r):
              $badge = $r['status'] === 'Processing' ? 'badge-info' : 'badge-warning';
            ?>
            <tr>
              <td><?= htmlspecialchars($r['request_number']) ?></td>
              <td><?= htmlspecialchars($r['document_type']) ?></td>
              <td><?= date('M d, Y', strtotime($r['requested_date'])) ?></td>
              <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" style="text-align:center;color:#aaa;padding:16px">No active requests.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Announcements -->
    <div class="card">
      <h3>Barangay Announcements</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-top:16px">
        <?php if ($announcements): foreach ($announcements as $a):
          $c = $colorMap[$a['color']] ?? $colorMap['primary'];
          $dt = $a['publish_date'] ? date('M d, Y', strtotime($a['publish_date'])) : '';
        ?>
        <div style="background:<?= $c['bg'] ?>;border-left:4px solid <?= $c['border'] ?>;padding:16px;border-radius:8px">
          <h4 style="margin:0 0 8px;color:<?= $c['text'] ?>"><?= htmlspecialchars($a['title']) ?></h4>
          <p style="margin:0 0 6px;font-size:13px;color:var(--text-light)"><?= htmlspecialchars(mb_substr($a['description'], 0, 100)) ?>...</p>
          <small style="color:var(--text-light)"><?= $dt ?></small>
        </div>
        <?php endforeach; else: ?>
        <p class="text-muted">No announcements at this time.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
