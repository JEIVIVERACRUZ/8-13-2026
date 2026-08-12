<?php
// ============================================================
// issuance.php  — Admin: Document requests management
// ============================================================
session_start();
require_once 'includes/db.php';
requireAdminLogin();

// ── AJAX handler: update request status ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $id      = (int)($_POST['id'] ?? 0);
        $status  = trim($_POST['status'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        $validStatuses = ['Pending','Processing','Ready for Pickup','Completed','Rejected'];
        if (!in_array($status, $validStatuses)) {
            jsonError('Invalid status');
        }

        $completedDate = in_array($status, ['Completed','Ready for Pickup']) ? date('Y-m-d') : null;

        dbExecute(
            "UPDATE document_requests
             SET status=?, remarks=?, completed_date=?, processed_by=?, updated_at=NOW()
             WHERE id=?",
            [$status, $remarks, $completedDate, $_SESSION['admin_id'], $id]
        );
        logActivity("Updated document request #$id to $status", 'Issuance');
        jsonOk([], 'Status updated.');
    }
    exit;
}

// ── Stats ────────────────────────────────────────────────────
$statsRow = dbFetchOne("
  SELECT
    SUM(DATE(created_at)=CURDATE()) AS today,
    SUM(status IN ('Ready for Pickup','Completed')) AS approved,
    SUM(status IN ('Pending','Processing')) AS pending
  FROM document_requests
") ?? [];

// ── Filter / Search ──────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$filterStatus= trim($_GET['status'] ?? '');
$conditions  = [];
$params      = [];

if ($search) {
    $conditions[] = "(CONCAT(r.first_name,' ',r.last_name) LIKE ? OR dr.request_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterStatus) {
    $conditions[] = "dr.status=?";
    $params[] = $filterStatus;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$requests = dbFetchAll("
  SELECT dr.*,
         CONCAT(r.first_name,' ',r.last_name) AS resident_name,
         r.barangay_id
  FROM document_requests dr
  JOIN residents r ON dr.resident_id = r.id
  $where
  ORDER BY dr.created_at DESC
  LIMIT 50
", $params);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Issuance / Documents — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Issuance / Documents</h3>
        <div class="text-muted">Process certificates, clearances, and document requests.</div>
      </div>
    </div>

    <div class="stats">
      <div class="stat">
        <h4>Requests Today</h4>
        <p data-target="<?= (int)($statsRow['today'] ?? 0) ?>">0</p>
        <div class="stat-icon">📄</div>
        <div class="stat-change positive">Today</div>
      </div>
      <div class="stat">
        <h4>Approved / Ready</h4>
        <p data-target="<?= (int)($statsRow['approved'] ?? 0) ?>">0</p>
        <div class="stat-icon">✅</div>
        <div class="stat-change positive">Done</div>
      </div>
      <div class="stat">
        <h4>Pending</h4>
        <p data-target="<?= (int)($statsRow['pending'] ?? 0) ?>">0</p>
        <div class="stat-icon">⏳</div>
        <div class="stat-change warning">Review required</div>
      </div>
    </div>

    <!-- Filter -->
    <div class="card">
      <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="flex:1;min-width:180px;margin:0">
          <label>Search Resident / Request ID</label>
          <input type="text" name="search" placeholder="Name or REQ-..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="form-group" style="margin:0;min-width:160px">
          <label>Filter by Status</label>
          <select name="status">
            <option value="">All</option>
            <?php foreach (['Pending','Processing','Ready for Pickup','Completed','Rejected'] as $s): ?>
              <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a href="issuance.php" class="btn">Clear</a>
      </form>
    </div>

    <div class="card">
      <h3>Document Requests</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Request ID</th>
              <th>Resident</th>
              <th>Document</th>
              <th>Requested Date</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($requests): foreach ($requests as $req):
              $badge = match($req['status']) {
                  'Completed'        => 'badge-success',
                  'Ready for Pickup' => 'badge-success',
                  'Processing'       => 'badge-info',
                  'Rejected'         => 'badge-danger',
                  default            => 'badge-warning',
              };
            ?>
            <tr>
              <td><?= htmlspecialchars($req['request_number']) ?></td>
              <td>
                <?= htmlspecialchars($req['resident_name']) ?>
                <small class="text-muted" style="display:block"><?= htmlspecialchars($req['barangay_id']) ?></small>
              </td>
              <td><?= htmlspecialchars($req['document_type']) ?></td>
              <td><?= $req['requested_date'] ? date('M d, Y', strtotime($req['requested_date'])) : '—' ?></td>
              <td><?= $req['due_date']       ? date('M d, Y', strtotime($req['due_date']))       : '—' ?></td>
              <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($req['status']) ?></span></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="btn btn-sm" style="background:#3b82f6;color:white;padding:6px 10px;border:none;border-radius:6px;cursor:pointer;font-size:12px"
                  onclick="openStatusModal(<?= $req['id'] ?>, '<?= htmlspecialchars($req['status']) ?>', `<?= addslashes($req['remarks'] ?? '') ?>`)">
                  Edit Status
                </button>
                <button class="btn btn-sm" style="background:#10b981;color:white;padding:6px 10px;border:none;border-radius:6px;cursor:pointer;font-size:12px"
                  onclick="window.open('print_document.php?id=<?= $req['id'] ?>', '_blank')">
                  🖨️ Print
                </button>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:#aaa;padding:20px;">No document requests found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Status Edit Modal -->
<div id="statusModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;padding:28px;width:90%;max-width:420px">
    <h3 style="margin-top:0">Update Request Status</h3>
    <div style="display:grid;gap:16px">
      <div class="form-group">
        <label>Status</label>
        <select id="modalStatus" class="form-control">
          <option value="Pending">Pending</option>
          <option value="Processing">Processing</option>
          <option value="Ready for Pickup">Ready for Pickup</option>
          <option value="Completed">Completed</option>
          <option value="Rejected">Rejected</option>
        </select>
      </div>
      <div class="form-group">
        <label>Remarks (optional)</label>
        <textarea id="modalRemarks" class="form-control" rows="3" placeholder="Notes for the resident..."></textarea>
      </div>
      <input type="hidden" id="modalId">
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" style="flex:1" onclick="saveStatus()">Save</button>
        <button class="btn btn-secondary" style="flex:1" onclick="closeStatusModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
function openStatusModal(id, status, remarks) {
  document.getElementById('modalId').value      = id;
  document.getElementById('modalStatus').value  = status;
  document.getElementById('modalRemarks').value = remarks;
  document.getElementById('statusModal').style.display = 'flex';
}
function closeStatusModal() {
  document.getElementById('statusModal').style.display = 'none';
}
async function saveStatus() {
  const fd = new FormData();
  fd.append('action',  'update_status');
  fd.append('id',      document.getElementById('modalId').value);
  fd.append('status',  document.getElementById('modalStatus').value);
  fd.append('remarks', document.getElementById('modalRemarks').value);

  const res  = await fetch('issuance.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.success) {
    showToast('Status updated!', 'success');
    closeStatusModal();
    setTimeout(() => location.reload(), 700);
  } else {
    showToast(data.message || 'Error', 'error');
  }
}
</script>
</body>
</html>
