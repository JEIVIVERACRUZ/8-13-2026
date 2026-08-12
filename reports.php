<?php
// ============================================================
// reports.php  — Admin: Reports management
// ============================================================
session_start();
require_once 'includes/db.php';
requireAdminLogin();

$stats = dbFetchOne("SELECT * FROM v_dashboard_stats") ?? [];

// Recent "reports" are just document_requests grouped
$reports = dbFetchAll("
  SELECT
    document_type AS report_name,
    'Document Report' AS type,
    MAX(created_at) AS created,
    SUM(status IN ('Completed','Ready for Pickup')) AS done,
    COUNT(*) AS total
  FROM document_requests
  WHERE YEAR(created_at) = YEAR(CURDATE())
  GROUP BY document_type
  ORDER BY total DESC
");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reports Management — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Reports Management</h3>
        <div class="text-muted">Generate and export key barangay analytics reports.</div>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary btn-sm" onclick="window.print()">🖨️ Print Report</button>
      </div>
    </div>

    <div class="card">
      <div class="form-grid">
        <div class="form-group">
          <label>Report Type</label>
          <select id="reportFilter" onchange="showToast('Filter applied','info')">
            <option>Population Report</option>
            <option>Request Report</option>
            <option>Household Report</option>
          </select>
        </div>
        <div class="form-group">
          <label>Start Date</label>
          <input type="date">
        </div>
        <div class="form-group">
          <label>End Date</label>
          <input type="date">
        </div>
      </div>
    </div>

    <div class="stats">
      <div class="stat">
        <h4>Population Summary</h4>
        <p data-target="<?= (int)($stats['total_residents'] ?? 0) ?>">0</p>
        <div class="stat-icon">📈</div>
        <div class="stat-change positive">Total residents</div>
      </div>
      <div class="stat">
        <h4>Requests Logged</h4>
        <p data-target="<?= (int)($stats['total_requests'] ?? 0) ?>">0</p>
        <div class="stat-icon">📑</div>
        <div class="stat-change positive">All time</div>
      </div>
      <div class="stat">
        <h4>Household Records</h4>
        <p data-target="<?= (int)($stats['total_households'] ?? 0) ?>">0</p>
        <div class="stat-icon">🏘️</div>
        <div class="stat-change positive">Active</div>
      </div>
    </div>

    <div class="card">
      <h3>Document Request Summary (This Year)</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr><th>Document Type</th><th>Type</th><th>Last Activity</th><th>Completed</th><th>Total</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if ($reports): foreach ($reports as $r):
              $pct   = $r['total'] > 0 ? round($r['done'] / $r['total'] * 100) : 0;
              $badge = $pct >= 80 ? 'badge-success' : ($pct >= 40 ? 'badge-info' : 'badge-warning');
            ?>
            <tr>
              <td><?= htmlspecialchars($r['report_name']) ?></td>
              <td><?= htmlspecialchars($r['type']) ?></td>
              <td><?= date('M d, Y', strtotime($r['created'])) ?></td>
              <td><?= (int)$r['done'] ?> / <?= (int)$r['total'] ?></td>
              <td><?= (int)$r['total'] ?></td>
              <td><span class="badge <?= $badge ?>"><?= $pct ?>% done</span></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No requests recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
