<?php
// ============================================================
// resident_appointments.php  — Resident: Book & manage appointments
// ============================================================
session_start();
require_once 'includes/db.php';
requireResidentLogin();

$residentId = $_SESSION['resident_id'];
$officeInfo = getSiteSettings();

// ── AJAX handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'book') {
        $purpose = trim($_POST['purpose']   ?? '');
        $date    = trim($_POST['date']      ?? '');
        $time    = trim($_POST['time']      ?? '');
        $notes   = trim($_POST['notes']     ?? '');

        if (!$purpose || !$date || !$time) jsonError('Purpose, date, and time are required.');
        if ($date < date('Y-m-d')) jsonError('Please select a future date.');

        dbInsert(
            "INSERT INTO appointments (resident_id, purpose, preferred_date, preferred_time, notes, status)
             VALUES (?,?,?,?,?,'Pending')",
            [$residentId, $purpose, $date, $time, $notes]
        );
        logActivity("Booked appointment: $purpose on $date", 'Appointments', 'resident');
        jsonOk([], 'Appointment booked successfully!');
    }
    if ($_POST['action'] === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute(
            "UPDATE appointments SET status='Cancelled' WHERE id=? AND resident_id=?",
            [$id, $residentId]
        );
        jsonOk([], 'Appointment cancelled.');
    }
    exit;
}

// ── Data ──────────────────────────────────────────────────────
$upcoming = dbFetchAll(
    "SELECT * FROM appointments WHERE resident_id=? AND status IN ('Pending','Confirmed','Rescheduled')
     ORDER BY preferred_date ASC",
    [$residentId]
);
$past = dbFetchAll(
    "SELECT * FROM appointments WHERE resident_id=? AND status IN ('Completed','Cancelled')
     ORDER BY preferred_date DESC LIMIT 10",
    [$residentId]
);
$upcomingCount = count($upcoming);
$pastCount     = count($past);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Appointments — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_resident.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Book Appointment</h3>
        <div class="text-muted">Schedule your visit to the barangay office</div>
      </div>
    </div>

    <div class="stats">
      <div class="stat">
        <h4>Upcoming Appointments</h4>
        <p data-target="<?= $upcomingCount ?>">0</p>
        <div class="stat-icon">📅</div>
        <div class="stat-change positive">Scheduled</div>
      </div>
      <div class="stat">
        <h4>Office Hours</h4>
        <p style="font-size:15px">Mon–Fri<br>8AM–5PM</p>
        <div class="stat-icon">🕐</div>
        <div class="stat-change positive">Open today</div>
      </div>
      <div class="stat">
        <h4>Past Visits</h4>
        <p data-target="<?= $pastCount ?>">0</p>
        <div class="stat-icon">✅</div>
        <div class="stat-change positive">History</div>
      </div>
    </div>

    <!-- Book Form -->
    <div class="card">
      <h3>Book New Appointment</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Purpose of Visit *</label>
          <select id="apPurpose" class="form-control">
            <option value="">Select...</option>
            <option>Request Document</option>
            <option>Update Records</option>
            <option>Certificate of Residency</option>
            <option>Permit Application</option>
            <option>General Inquiry</option>
          </select>
        </div>
        <div class="form-group">
          <label>Preferred Date *</label>
          <input type="date" id="apDate" class="form-control" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Preferred Time *</label>
          <select id="apTime" class="form-control">
            <option>8:00 AM - 9:00 AM</option>
            <option>9:00 AM - 10:00 AM</option>
            <option>10:00 AM - 11:00 AM</option>
            <option>11:00 AM - 12:00 PM</option>
            <option>1:00 PM - 2:00 PM</option>
            <option>2:00 PM - 3:00 PM</option>
            <option>3:00 PM - 4:00 PM</option>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Additional Notes</label>
          <textarea id="apNotes" class="form-control" rows="2" placeholder="Any special requests or additional info"></textarea>
        </div>
      </div>
      <button class="btn btn-primary btn-sm" onclick="bookAppointment()">📅 Book Appointment</button>
    </div>

    <!-- Upcoming -->
    <div class="card">
      <h3>Upcoming Appointments</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody id="upcomingBody">
            <?php if ($upcoming): foreach ($upcoming as $ap):
              $badge = match($ap['status']) {
                  'Confirmed' => 'badge-success',
                  'Pending'   => 'badge-warning',
                  default     => 'badge-info',
              };
            ?>
            <tr data-id="<?= $ap['id'] ?>">
              <td><?= date('M d, Y', strtotime($ap['preferred_date'])) ?></td>
              <td><?= htmlspecialchars($ap['preferred_time']) ?></td>
              <td><?= htmlspecialchars($ap['purpose']) ?></td>
              <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($ap['status']) ?></span></td>
              <td>
                <button class="btn btn-secondary btn-sm" onclick="cancelAppointment(<?= $ap['id'] ?>, this)">Cancel</button>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" style="text-align:center;color:#aaa;padding:16px">No upcoming appointments.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Office info -->
    <div class="card">
      <h3>Office Information</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px">
        <div><h4>Address</h4><p><?= htmlspecialchars($officeInfo['barangay_name'] ?? 'Barangay San Isidro') ?><br><?= htmlspecialchars($officeInfo['address'] ?? '') ?></p></div>
        <div><h4>Contact</h4><p>Phone: <?= htmlspecialchars($officeInfo['contact_number'] ?? '—') ?><br>Email: <?= htmlspecialchars($officeInfo['email'] ?? '—') ?><br>Hours: Mon–Fri 8AM–5PM</p></div>
        <div><h4>Services</h4><p>• Document Issuance<br>• Barangay Clearance<br>• Permits &amp; Licenses<br>• Mediation Services</p></div>
      </div>
    </div>

    <!-- Past -->
    <div class="card">
      <h3>Past Appointments</h3>
      <div class="table-container">
        <table>
          <thead><tr><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th></tr></thead>
          <tbody>
            <?php if ($past): foreach ($past as $ap):
              $badge = $ap['status']==='Completed' ? 'badge-success' : 'badge-danger';
            ?>
            <tr>
              <td><?= date('M d, Y', strtotime($ap['preferred_date'])) ?></td>
              <td><?= htmlspecialchars($ap['preferred_time']) ?></td>
              <td><?= htmlspecialchars($ap['purpose']) ?></td>
              <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($ap['status']) ?></span></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" style="text-align:center;color:#aaa;padding:16px">No past appointments.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
async function bookAppointment() {
  const purpose = document.getElementById('apPurpose').value;
  const date    = document.getElementById('apDate').value;
  const time    = document.getElementById('apTime').value;
  const notes   = document.getElementById('apNotes').value;

  if (!purpose || !date) { showToast('Please fill in purpose and date.','error'); return; }

  const fd = new FormData();
  fd.append('action',  'book');
  fd.append('purpose', purpose);
  fd.append('date',    date);
  fd.append('time',    time);
  fd.append('notes',   notes);

  const res  = await fetch('resident_appointments.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) { showToast(data.message,'success'); setTimeout(()=>location.reload(),800); }
  else showToast(data.message,'error');
}

async function cancelAppointment(id, btn) {
  if (!confirm('Cancel this appointment?')) return;
  const fd = new FormData();
  fd.append('action','cancel'); fd.append('id',id);
  const res  = await fetch('resident_appointments.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) { showToast(data.message,'info'); btn.closest('tr').remove(); }
}
</script>
</body>
</html>
