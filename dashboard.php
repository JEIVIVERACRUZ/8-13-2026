<?php
// ============================================================
// dashboard.php  — Admin dashboard with live data
// ============================================================
session_start();
require_once 'includes/db.php';
requireAdminLogin();

$stats = dbFetchOne("SELECT * FROM v_dashboard_stats") ?? [];

// Age group counts
$ageGroups = dbFetchAll("
  SELECT
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN 0  AND 12  THEN 1 ELSE 0 END) AS g0_12,
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN 13 AND 25  THEN 1 ELSE 0 END) AS g13_25,
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN 26 AND 40  THEN 1 ELSE 0 END) AS g26_40,
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN 41 AND 60  THEN 1 ELSE 0 END) AS g41_60,
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) > 60               THEN 1 ELSE 0 END) AS g60plus
  FROM residents WHERE status != 'Inactive'
");
$age = $ageGroups[0] ?? [];

// Gender counts
$genderData = dbFetchAll("SELECT gender, COUNT(*) AS cnt FROM residents WHERE status!='Inactive' GROUP BY gender");
$genderMap  = array_column($genderData, 'cnt', 'gender');

// Zone distribution
$zoneData = dbFetchAll("SELECT zone_purok, COUNT(*) AS cnt FROM residents WHERE status!='Inactive' AND zone_purok!='' GROUP BY zone_purok ORDER BY cnt DESC LIMIT 6");

// Monthly requests (last 8 months)
$monthlyReqs = dbFetchAll("
  SELECT DATE_FORMAT(created_at,'%b') AS month_label, COUNT(*) AS cnt
  FROM document_requests
  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 8 MONTH)
  GROUP BY YEAR(created_at), MONTH(created_at)
  ORDER BY YEAR(created_at), MONTH(created_at)
");

// Recent activity
$recent = dbFetchAll("
  SELECT al.action, al.module, al.created_at, al.user_type,
         COALESCE(a.name, CONCAT(r.first_name,' ',r.last_name), 'Unknown') AS actor_name
  FROM activity_log al
  LEFT JOIN admin_users a ON al.user_type='admin'    AND al.user_id=a.id
  LEFT JOIN residents   r ON al.user_type='resident' AND al.user_id=r.id
  ORDER BY al.created_at DESC LIMIT 10
");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Dashboard</h3>
        <div class="text-muted">Overview of barangay data, requests and resident insights.</div>
      </div>
      <div class="topbar-actions">
        <a href="reports.php" class="btn btn-secondary btn-sm">Activity Log</a>
        <a href="issuance.php" class="btn btn-primary btn-sm">New Request</a>
      </div>
    </div>

    <div class="stats">
      <div class="stat">
        <h4>Total Residents</h4>
        <p data-target="<?= (int)($stats['total_residents'] ?? 0) ?>">0</p>
        <div class="stat-icon">👥</div>
        <div class="stat-change positive">Registered</div>
      </div>
      <div class="stat">
        <h4>Pending Requests</h4>
        <p data-target="<?= (int)($stats['pending_requests'] ?? 0) ?>">0</p>
        <div class="stat-icon">🧾</div>
        <div class="stat-change warning">Needs action</div>
      </div>
      <div class="stat">
        <h4>System Activities</h4>
        <p data-target="<?= dbFetchOne('SELECT COUNT(*) AS c FROM activity_log WHERE DATE(created_at) = CURDATE()')['c'] ?? 0 ?>">0</p>
        <div class="stat-icon">⚡</div>
        <div class="stat-change positive">Activity logged today</div>
      </div>
    </div>

    <div class="hero">
      <div class="overlay">
        <h1>Barangay San Isidro Analytics</h1>
        <p>Monitor population growth, document requests, and zone activity with live dashboard indicators.</p>
      </div>
    </div>

    <div class="content-cards">
      <div class="card quick-action-card" onclick="location.href='my_inhabitants.php'">
        <h4>Add Resident</h4>
        <small class="text-muted">Register new households and individuals</small>
      </div>
      <div class="card quick-action-card" onclick="location.href='issuance.php'">
        <h4>Create Document</h4>
        <small class="text-muted">Prepare certificates, clearances, and records</small>
      </div>
      <div class="card quick-action-card" onclick="location.href='reports.php'">
        <h4>View Reports</h4>
        <small class="text-muted">Open population and request summaries</small>
      </div>
      <div class="card quick-action-card" onclick="location.href='users.php'">
        <h4>Manage Users</h4>
        <small class="text-muted">Update admin and staff access</small>
      </div>
    </div>

    <div class="charts-container">
      <div class="chart-card">
        <h4>Residents by Age Group</h4>
        <canvas id="ageChart"></canvas>
      </div>
      <div class="chart-card">
        <h4>Monthly Document Requests</h4>
        <canvas id="requestsChart"></canvas>
      </div>
      <div class="chart-card">
        <h4>Male vs Female Population</h4>
        <canvas id="genderChart"></canvas>
      </div>
      <div class="chart-card">
        <h4>Household Distribution per Zone</h4>
        <canvas id="zoneChart"></canvas>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px">
        <h3>Recent Activity</h3>
      </div>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Actor</th>
              <th>Action</th>
              <th>Module</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recent): foreach ($recent as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['actor_name']) ?></td>
              <td><?= htmlspecialchars($r['action']) ?></td>
              <td><?= htmlspecialchars($r['module']) ?></td>
              <td><?= date('M d, Y g:i A', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" style="text-align:center;color:#aaa;padding:20px;">No recent activity</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/app.js"></script>
<script>
// Inject PHP data as JS variables
const ageData      = [<?= (int)($age['g0_12']??0) ?>,<?= (int)($age['g13_25']??0) ?>,<?= (int)($age['g26_40']??0) ?>,<?= (int)($age['g41_60']??0) ?>,<?= (int)($age['g60plus']??0) ?>];
const monthLabels  = <?= json_encode(array_column($monthlyReqs, 'month_label')) ?>;
const monthData    = <?= json_encode(array_map('intval', array_column($monthlyReqs, 'cnt'))) ?>;
const genderLabels = <?= json_encode(array_keys($genderMap)) ?>;
const genderData   = <?= json_encode(array_values($genderMap)) ?>;
const zoneLabels   = <?= json_encode(array_column($zoneData, 'zone_purok')) ?>;
const zoneData2    = <?= json_encode(array_map('intval', array_column($zoneData, 'cnt'))) ?>;

window.addEventListener('DOMContentLoaded', function() {
  // Age chart
  const ageCtx = document.getElementById('ageChart')?.getContext('2d');
  if (ageCtx) new Chart(ageCtx, {
    type:'bar',
    data:{ labels:['0-12','13-25','26-40','41-60','60+'], datasets:[{ label:'Residents', data:ageData, backgroundColor:['#3b82f6','#60a5fa','#7dd3fc','#38bdf8','#0ea5e9'], borderRadius:14, maxBarThickness:32 }] },
    options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true}, x:{grid:{display:false}} } }
  });

  // Monthly requests
  const reqCtx = document.getElementById('requestsChart')?.getContext('2d');
  if (reqCtx) new Chart(reqCtx, {
    type:'line',
    data:{ labels: monthLabels.length ? monthLabels : ['No data'], datasets:[{ label:'Requests', data: monthData.length ? monthData : [0], borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.14)', tension:0.35, fill:true, pointRadius:4, pointBackgroundColor:'#059669' }] },
    options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true}, x:{grid:{display:false}} } }
  });

  // Gender
  const genCtx = document.getElementById('genderChart')?.getContext('2d');
  if (genCtx) new Chart(genCtx, {
    type:'pie',
    data:{ labels: genderLabels.length ? genderLabels : ['No data'], datasets:[{ data: genderData.length ? genderData : [1], backgroundColor:['#3b82f6','#a855f7','#f97316'] }] },
    options:{ responsive:true, plugins:{legend:{position:'bottom'}} }
  });

  // Zone
  const zoneCtx = document.getElementById('zoneChart')?.getContext('2d');
  if (zoneCtx) new Chart(zoneCtx, {
    type:'doughnut',
    data:{ labels: zoneLabels.length ? zoneLabels : ['No data'], datasets:[{ data: zoneData2.length ? zoneData2 : [1], backgroundColor:['#f97316','#facc15','#34d399','#60a5fa','#a855f7','#f43f5e'], hoverOffset:10 }] },
    options:{ responsive:true, plugins:{legend:{position:'bottom'}} }
  });
});
</script>
</body>
</html>
