<?php
// ============================================================
// resident_documents.php  — Resident document requests
// ============================================================
session_start();
require_once 'includes/db.php';
requireResidentLogin();

$residentId = $_SESSION['resident_id'];

// ── AJAX handler: submit document request ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'submit_request') {
        $docType = trim($_POST['document_type'] ?? '');
        $purpose = trim($_POST['purpose']       ?? '');

        $validTypes = [
            'Barangay Clearance','Certificate of Residency','Barangay ID',
            'Certificate of Indigency','Business Permit','Proof of Residency','Other'
        ];
        if (!in_array($docType, $validTypes)) jsonError('Invalid document type.');

        $requestNo = generateRequestNumber();
        $today     = date('Y-m-d');
        $dueDate   = date('Y-m-d', strtotime('+3 days'));   // default 3 working days

        dbInsert(
            "INSERT INTO document_requests
               (request_number, resident_id, document_type, purpose, status, requested_date, due_date)
             VALUES (?,?,?,?,'Pending',?,?)",
            [$requestNo, $residentId, $docType, $purpose, $today, $dueDate]
        );
        logActivity("Submitted document request: $docType ($requestNo)", 'Documents', 'resident');
        jsonOk(['request_number' => $requestNo], "Request $requestNo submitted successfully!");
    }
    exit;
}

// ── Stats ─────────────────────────────────────────────────────
$ready      = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM document_requests WHERE resident_id=? AND status='Ready for Pickup'", [$residentId])['c'] ?? 0);
$processing = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM document_requests WHERE resident_id=? AND status IN ('Pending','Processing')", [$residentId])['c'] ?? 0);
$total      = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM document_requests WHERE resident_id=?", [$residentId])['c'] ?? 0);

// ── All requests ──────────────────────────────────────────────
$requests = dbFetchAll(
    "SELECT * FROM document_requests WHERE resident_id=? ORDER BY created_at DESC",
    [$residentId]
);

$docTypes = [
    ['icon'=>'🧾','label'=>'Barangay Clearance',     'days'=>'1–3 working days'],
    ['icon'=>'📜','label'=>'Certificate of Residency','days'=>'Same day'],
    ['icon'=>'🆔','label'=>'Barangay ID',             'days'=>'1–2 days'],
    ['icon'=>'🏛️','label'=>'Certificate of Indigency','days'=>'1–3 working days'],
    ['icon'=>'🏪','label'=>'Business Permit',         'days'=>'2–5 working days'],
    ['icon'=>'🗺️','label'=>'Proof of Residency',      'days'=>'1 working day'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Documents — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_resident.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>My Documents</h3>
        <div class="text-muted">Request, track, and manage barangay documents</div>
      </div>
    </div>

    <div class="stats">
      <div class="stat">
        <h4>Ready for Pickup</h4>
        <p data-target="<?= $ready ?>">0</p>
        <div class="stat-icon">✅</div>
        <div class="stat-change positive">Visit the office</div>
      </div>
      <div class="stat">
        <h4>Processing</h4>
        <p data-target="<?= $processing ?>">0</p>
        <div class="stat-icon">⏳</div>
        <div class="stat-change warning">In progress</div>
      </div>
      <div class="stat">
        <h4>Total Requests</h4>
        <p data-target="<?= $total ?>">0</p>
        <div class="stat-icon">📑</div>
        <div class="stat-change positive">Lifetime</div>
      </div>
    </div>

    <!-- Available Documents -->
    <div class="card">
      <h3>Available Documents</h3>
      <p class="text-muted">Click a document type to submit a request.</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-top:16px">
        <?php foreach ($docTypes as $dt): ?>
        <div class="card" style="text-align:center;cursor:pointer;padding:20px;border:2px solid transparent;transition:all 0.3s"
             onmouseover="this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)'"
             onmouseout="this.style.borderColor='transparent';this.style.background='white'">
          <div style="font-size:32px;margin-bottom:8px"><?= $dt['icon'] ?></div>
          <h4 style="margin:0 0 4px"><?= htmlspecialchars($dt['label']) ?></h4>
          <small class="text-muted"><?= htmlspecialchars($dt['days']) ?></small>
          <button class="btn btn-primary btn-sm" type="button" style="margin-top:12px;width:100%"
            onclick="openRequestModal('<?= htmlspecialchars($dt['label']) ?>')">Request</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- My Requests Table -->
    <div class="card">
      <h3>My Requests</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Request ID</th>
              <th>Document</th>
              <th>Requested Date</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody id="requestsBody">
            <?php if ($requests): foreach ($requests as $r):
              $badge = match($r['status']) {
                  'Completed'        => 'badge-success',
                  'Ready for Pickup' => 'badge-success',
                  'Processing'       => 'badge-info',
                  'Rejected'         => 'badge-danger',
                  default            => 'badge-warning',
              };
            ?>
            <tr>
              <td><?= htmlspecialchars($r['request_number']) ?></td>
              <td><?= htmlspecialchars($r['document_type']) ?></td>
              <td><?= date('M d, Y', strtotime($r['requested_date'])) ?></td>
              <td><?= $r['due_date'] ? date('M d, Y', strtotime($r['due_date'])) : '—' ?></td>
              <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($r['status']) ?></span></td>
              <td><?= htmlspecialchars($r['remarks'] ?? '—') ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#aaa;padding:16px">No requests yet. Select a document above to get started.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Request Modal -->
<div id="requestModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;padding:28px;width:90%;max-width:420px">
    <h3 style="margin-top:0">Request Document</h3>
    <p id="reqDocName" style="font-weight:600;color:var(--primary);margin:0 0 16px"></p>
    <div class="form-group">
      <label>Purpose / Reason</label>
      <textarea id="reqPurpose" class="form-control" rows="3" placeholder="State your purpose for requesting this document..."></textarea>
    </div>
    <input type="hidden" id="reqDocType">
    <div style="display:flex;gap:10px;margin-top:16px">
      <button class="btn btn-primary" style="flex:1" onclick="submitRequest()">Submit Request</button>
      <button class="btn btn-secondary" style="flex:1" onclick="document.getElementById('requestModal').style.display='none'">Cancel</button>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
function openRequestModal(docType) {
  document.getElementById('reqDocName').textContent = docType;
  document.getElementById('reqDocType').value       = docType;
  document.getElementById('reqPurpose').value       = '';
  document.getElementById('requestModal').style.display = 'flex';
}

async function submitRequest() {
  const docType = document.getElementById('reqDocType').value;
  const purpose = document.getElementById('reqPurpose').value.trim();

  const fd = new FormData();
  fd.append('action',        'submit_request');
  fd.append('document_type', docType);
  fd.append('purpose',       purpose);

  const res  = await fetch('resident_documents.php', { method:'POST', body:fd });
  const data = await res.json();

  if (data.success) {
    showToast(data.message, 'success');
    document.getElementById('requestModal').style.display = 'none';
    setTimeout(() => location.reload(), 900);
  } else {
    showToast(data.message, 'error');
  }
}
</script>
</body>
</html>
