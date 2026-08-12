<?php
// ============================================================
// my_inhabitants.php  — Admin: Resident records management
// ============================================================
session_start();
require_once 'includes/db.php';
requireAdminLogin();

// ── AJAX handlers ────────────────────────────────────────────

// Update resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_resident') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['full_name'] ?? '');
        $age     = (int)($_POST['age'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $status  = trim($_POST['status'] ?? '');

        // Split name into first/last
        $parts = explode(' ', $name, 2);
        $first = $parts[0];
        $last  = $parts[1] ?? '';

        dbExecute(
            "UPDATE residents SET first_name=?, last_name=?, street_address=?, status=?,
             date_of_birth=DATE_SUB(CURDATE(), INTERVAL ? YEAR), updated_at=NOW()
             WHERE id=?",
            [$first, $last, $address, $status, $age, $id]
        );
        logActivity("Updated resident ID $id", 'My Profile');
        jsonOk([], 'Resident updated successfully.');
    }

    if ($_POST['action'] === 'add_resident') {
        $firstName  = trim($_POST['first_name']  ?? '');
        $lastName   = trim($_POST['last_name']   ?? '');
        $email      = trim($_POST['email']       ?? '');
        $phone      = trim($_POST['phone']       ?? '');
        $dob        = trim($_POST['dob']         ?? '');
        $gender     = trim($_POST['gender']      ?? '');
        $street     = trim($_POST['street']      ?? '');
        $zone       = trim($_POST['zone']        ?? '');
        $house      = trim($_POST['house']       ?? '');
        $occupation = trim($_POST['occupation']  ?? '');
        $password   = $_POST['password']         ?? '';
        $status     = trim($_POST['status']      ?? 'Pending');

        if (!$firstName || !$lastName)        jsonError('First and last name are required.');
        if (!$email)                           jsonError('Email is required.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Invalid email address.');
        if (strlen($password) < 8)            jsonError('Password must be at least 8 characters.');

        $exists = dbFetchOne('SELECT id FROM residents WHERE email=? LIMIT 1', [$email]);
        if ($exists) jsonError('An account with that email already exists.');

        $barangayId   = generateResidentId();
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        dbInsert(
            "INSERT INTO residents
               (barangay_id, first_name, last_name, email, phone,
                date_of_birth, gender, street_address, zone_purok, house_number,
                occupation, password_hash, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $barangayId, $firstName, $lastName, $email, $phone,
                $dob ?: null, $gender, $street, $zone, $house,
                $occupation, $passwordHash, $status,
            ]
        );
        logActivity("Admin added resident: $barangayId", 'My Profile');
        jsonOk(['barangay_id' => $barangayId], "Resident added! Barangay ID: $barangayId");
    }
    exit;
}

// ── Query params ──────────────────────────────────────────────
$search   = trim($_GET['search'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 5;
$offset   = ($page - 1) * $perPage;

$where  = $search
    ? "WHERE (CONCAT(first_name,' ',last_name) LIKE ? OR barangay_id LIKE ?)"
    : '';
$params = $search ? ["%$search%", "%$search%"] : [];

$total = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM residents $where",
    $params
)['c'] ?? 0);

$residents = dbFetchAll(
    "SELECT id, barangay_id,
            CONCAT(first_name,' ',last_name) AS full_name,
            TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) AS age,
            street_address, status
     FROM residents $where
     ORDER BY created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$totalPages = (int)ceil($total / $perPage);

// Stats
$statsRow = dbFetchOne("SELECT
    COUNT(*) AS total,
    SUM(is_household_head) AS heads,
    (SELECT COUNT(*) FROM households) AS households
  FROM residents WHERE status != 'Inactive'") ?? [];

// Featured resident (latest verified)
$featured = dbFetchOne("SELECT *, TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) AS age,
  CONCAT(first_name,' ',last_name) AS full_name
  FROM residents WHERE status='Verified' ORDER BY created_at DESC LIMIT 1");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Inhabitant Profile — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>My Inhabitant Profile</h3>
        <div class="text-muted">Review resident details and household information.</div>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('addResidentModal').style.display='flex'">+ Add Resident</button>
      </div>
    </div>

    <div class="stats">
      <div class="stat">
        <h4>Registered Residents</h4>
        <p data-target="<?= (int)($statsRow['total'] ?? 0) ?>">0</p>
        <div class="stat-icon">👤</div>
        <div class="stat-change positive">Updated today</div>
      </div>
      <div class="stat">
        <h4>Family Heads</h4>
        <p data-target="<?= (int)($statsRow['heads'] ?? 0) ?>">0</p>
        <div class="stat-icon">🏠</div>
        <div class="stat-change positive">Household heads</div>
      </div>
      <div class="stat">
        <h4>Active Households</h4>
        <p data-target="<?= (int)($statsRow['households'] ?? 0) ?>">0</p>
        <div class="stat-icon">📊</div>
        <div class="stat-change positive">Data refreshed</div>
      </div>
    </div>

    <!-- Featured Resident -->
    <div class="card">
      <h3>Featured Resident <?= $featured ? '— ' . htmlspecialchars($featured['barangay_id']) : '' ?></h3>
      <?php if ($featured): ?>
      <div class="form-grid">
        <div>
          <div class="form-group"><label>Name</label><input type="text" value="<?= htmlspecialchars($featured['full_name']) ?>" readonly></div>
          <div class="form-group"><label>Age</label><input type="text" value="<?= htmlspecialchars($featured['age'] ?? '') ?>" readonly></div>
          <div class="form-group"><label>Barangay ID</label><input type="text" value="<?= htmlspecialchars($featured['barangay_id']) ?>" readonly></div>
          <div class="form-group"><label>Occupation</label><input type="text" value="<?= htmlspecialchars($featured['occupation'] ?? '') ?>" readonly></div>
        </div>
        <div>
          <div class="form-group"><label>Address</label><textarea rows="3" readonly><?= htmlspecialchars($featured['street_address'] ?? '') ?></textarea></div>
          <div class="form-group"><label>Zone / Purok</label><input type="text" value="<?= htmlspecialchars($featured['zone_purok'] ?? '') ?>" readonly></div>
          <div class="form-group"><label>Status</label><input type="text" value="<?= htmlspecialchars($featured['status']) ?>" readonly></div>
        </div>
      </div>
      <?php else: ?>
        <p class="text-muted">No verified residents yet. Approve a resident to see them here.</p>
      <?php endif; ?>
    </div>

    <!-- Search -->
    <div class="card">
      <h3>Search Residents</h3>
      <form method="GET" style="display:flex;gap:12px;align-items:flex-end">
        <div class="form-group" style="flex:1;margin:0">
          <label>Search by Name or Barangay ID</label>
          <input type="text" name="search" id="searchInput" placeholder="Type name or ID..." value="<?= htmlspecialchars($search) ?>" style="margin:0">
        </div>
        <button class="btn btn-primary" type="submit">Search</button>
        <a href="my_inhabitants.php" class="btn">Clear</a>
      </form>
    </div>

    <!-- All Residents Table -->
    <div class="card">
      <h3>All Residents</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Age</th>
              <th>Barangay ID</th>
              <th>Address</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="residentsTable">
            <?php if ($residents): foreach ($residents as $r):
              $badge = match($r['status']) {
                  'Verified'  => 'badge-success',
                  'Pending'   => 'badge-info',
                  default     => 'badge-danger',
              };
            ?>
            <tr data-id="<?= $r['id'] ?>">
              <td><?= htmlspecialchars($r['full_name']) ?></td>
              <td><?= htmlspecialchars($r['age'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['barangay_id']) ?></td>
              <td><?= htmlspecialchars($r['street_address'] ?? '') ?></td>
              <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($r['status']) ?></span></td>
              <td>
                <button class="btn btn-sm" style="background:#3b82f6;color:white;padding:6px 12px;border:none;border-radius:6px;cursor:pointer;font-size:12px"
                  onclick="openEdit(this)"
                  data-id="<?= $r['id'] ?>"
                  data-name="<?= htmlspecialchars($r['full_name']) ?>"
                  data-age="<?= (int)($r['age'] ?? 0) ?>"
                  data-bid="<?= htmlspecialchars($r['barangay_id']) ?>"
                  data-address="<?= htmlspecialchars($r['street_address'] ?? '') ?>"
                  data-status="<?= htmlspecialchars($r['status']) ?>">
                  Edit
                </button>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px;">No residents found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:14px;flex-wrap:wrap;gap:8px">
        <span class="text-muted" style="font-size:13px">
          Showing <?= $total ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> residents
        </span>
        <div style="display:flex;gap:8px">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">← Previous</a>
          <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled>← Previous</button>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">Next →</a>
          <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled>Next →</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;padding:30px;width:90%;max-width:480px;max-height:90vh;overflow-y:auto">
    <h3 style="margin-top:0">Edit Resident Information</h3>
    <div style="display:grid;gap:16px">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" id="editName" class="form-control">
      </div>
      <div class="form-group">
        <label>Age</label>
        <input type="number" id="editAge" class="form-control">
      </div>
      <div class="form-group">
        <label>Barangay ID</label>
        <input type="text" id="editBID" class="form-control" readonly style="background:#f3f4f6">
      </div>
      <div class="form-group">
        <label>Address</label>
        <textarea id="editAddress" class="form-control" rows="3"></textarea>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select id="editStatus" class="form-control">
          <option value="Verified">Verified</option>
          <option value="Pending">Pending</option>
          <option value="Inactive">Inactive</option>
        </select>
      </div>
      <input type="hidden" id="editId">
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" style="flex:1" onclick="saveResident()">Save Changes</button>
        <button class="btn btn-secondary" style="flex:1" onclick="closeEdit()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Resident Modal -->
<div id="addResidentModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;padding:30px;width:90%;max-width:640px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="margin:0">Add New Resident</h3>
      <button onclick="document.getElementById('addResidentModal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af">✕</button>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>First Name *</label>
        <input type="text" id="addFirstName" class="form-control" placeholder="Juan">
      </div>
      <div class="form-group">
        <label>Last Name *</label>
        <input type="text" id="addLastName" class="form-control" placeholder="Dela Cruz">
      </div>
      <div class="form-group">
        <label>Email *</label>
        <input type="email" id="addEmail" class="form-control" placeholder="juan@example.com">
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="tel" id="addPhone" class="form-control" placeholder="0917 555 0123">
      </div>
      <div class="form-group">
        <label>Date of Birth</label>
        <input type="date" id="addDob" class="form-control">
      </div>
      <div class="form-group">
        <label>Gender</label>
        <select id="addGender" class="form-control">
          <option value="">Select...</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Street Address</label>
        <input type="text" id="addStreet" class="form-control" placeholder="123 Sampaguita St.">
      </div>
      <div class="form-group">
        <label>Zone / Purok</label>
        <select id="addZone" class="form-control">
          <option value="">Select...</option>
          <?php
          $puroks = dbFetchAll("SELECT name FROM puroks ORDER BY name");
          $zones  = $puroks ?: [['name'=>'Zone 1'],['name'=>'Zone 2'],['name'=>'Zone 3'],['name'=>'Zone 4']];
          foreach ($zones as $z):
          ?>
          <option value="<?= htmlspecialchars($z['name']) ?>"><?= htmlspecialchars($z['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>House Number</label>
        <input type="text" id="addHouse" class="form-control" placeholder="Lot 5">
      </div>
      <div class="form-group">
        <label>Occupation</label>
        <input type="text" id="addOccupation" class="form-control" placeholder="e.g., Teacher">
      </div>
      <div class="form-group">
        <label>Password * (min 8 chars)</label>
        <input type="password" id="addPassword" class="form-control" placeholder="Set account password">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select id="addStatus" class="form-control">
          <option value="Pending">Pending</option>
          <option value="Verified">Verified</option>
          <option value="Inactive">Inactive</option>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:20px">
      <button class="btn btn-primary" style="flex:1" onclick="submitAddResident()">Add Resident</button>
      <button class="btn btn-secondary" style="flex:1" onclick="document.getElementById('addResidentModal').style.display='none'">Cancel</button>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
function openEdit(btn) {
  document.getElementById('editId').value      = btn.dataset.id;
  document.getElementById('editName').value    = btn.dataset.name;
  document.getElementById('editAge').value     = btn.dataset.age;
  document.getElementById('editBID').value     = btn.dataset.bid;
  document.getElementById('editAddress').value = btn.dataset.address;
  document.getElementById('editStatus').value  = btn.dataset.status;
  document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() {
  document.getElementById('editModal').style.display = 'none';
}
async function saveResident() {
  const id      = document.getElementById('editId').value;
  const name    = document.getElementById('editName').value;
  const age     = document.getElementById('editAge').value;
  const address = document.getElementById('editAddress').value;
  const status  = document.getElementById('editStatus').value;

  const fd = new FormData();
  fd.append('action', 'update_resident');
  fd.append('id', id);
  fd.append('full_name', name);
  fd.append('age', age);
  fd.append('address', address);
  fd.append('status', status);

  const res  = await fetch('my_inhabitants.php', { method:'POST', body: fd });
  const data = await res.json();

  if (data.success) {
    showToast('Resident updated!', 'success');
    closeEdit();
    setTimeout(() => location.reload(), 800);
  } else {
    showToast(data.message || 'Error', 'error');
  }
}

async function submitAddResident() {
  const fd = new FormData();
  fd.append('action',      'add_resident');
  fd.append('first_name',  document.getElementById('addFirstName').value.trim());
  fd.append('last_name',   document.getElementById('addLastName').value.trim());
  fd.append('email',       document.getElementById('addEmail').value.trim());
  fd.append('phone',       document.getElementById('addPhone').value.trim());
  fd.append('dob',         document.getElementById('addDob').value);
  fd.append('gender',      document.getElementById('addGender').value);
  fd.append('street',      document.getElementById('addStreet').value.trim());
  fd.append('zone',        document.getElementById('addZone').value);
  fd.append('house',       document.getElementById('addHouse').value.trim());
  fd.append('occupation',  document.getElementById('addOccupation').value.trim());
  fd.append('password',    document.getElementById('addPassword').value);
  fd.append('status',      document.getElementById('addStatus').value);

  const res  = await fetch('my_inhabitants.php', { method:'POST', body: fd });
  const data = await res.json();

  if (data.success) {
    showToast(data.message, 'success');
    document.getElementById('addResidentModal').style.display = 'none';
    setTimeout(() => location.reload(), 900);
  } else {
    showToast(data.message, 'error');
  }
}
</script>
</body>
</html>