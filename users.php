<?php
// ============================================================
// users.php  — Admin: Staff account management
// ============================================================
session_start();
require_once 'includes/db.php';
requireAdminLogin();

// ── AJAX handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_user') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $username = trim($_POST['username'] ?? '');
        $role     = trim($_POST['role']     ?? 'Staff');
        $password = $_POST['password']      ?? '';

        if (!$name || !$email || !$username || !$password) jsonError('All fields are required.');
        if (strlen($password) < 6) jsonError('Password must be at least 6 characters.');

        $exists = dbFetchOne("SELECT id FROM admin_users WHERE email=? OR username=?", [$email, $username]);
        if ($exists) jsonError('Email or username already in use.');

        dbInsert(
            "INSERT INTO admin_users (name, email, username, password_hash, role, status) VALUES (?,?,?,?,?,'Active')",
            [$name, $email, $username, password_hash($password, PASSWORD_BCRYPT), $role]
        );
        logActivity("Added staff user: $username", 'Users');
        jsonOk([], 'User added successfully.');
    }

    if ($action === 'toggle_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if (!in_array($status, ['Active','Inactive'])) jsonError('Invalid status.');
        if ($id === (int)$_SESSION['admin_id']) jsonError('Cannot deactivate your own account.');
        dbExecute("UPDATE admin_users SET status=? WHERE id=?", [$status, $id]);
        logActivity("Changed user #$id status to $status", 'Users');
        jsonOk([], "User $status.");
    }

    if ($action === 'reset_password') {
        $id       = (int)($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) jsonError('Password must be at least 6 characters.');
        dbExecute(
            "UPDATE admin_users SET password_hash=? WHERE id=?",
            [password_hash($password, PASSWORD_BCRYPT), $id]
        );
        logActivity("Reset password for user #$id", 'Users');
        jsonOk([], 'Password updated.');
    }
    exit;
}

$search = trim($_GET['search'] ?? '');
$role   = trim($_GET['role']   ?? '');
$where  = [];
$params = [];

if ($search) { $where[] = "(name LIKE ? OR email LIKE ? OR username LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($role)   { $where[] = "role=?"; $params[] = $role; }

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$users = dbFetchAll("SELECT * FROM admin_users $whereStr ORDER BY created_at DESC", $params);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>User Management — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>User Management</h3>
        <div class="text-muted">Search, edit and manage staff accounts and permissions.</div>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'">+ Add User</button>
      </div>
    </div>

    <!-- Search / Filter -->
    <div class="card">
      <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="flex:1;min-width:180px;margin:0">
          <label>Search Users</label>
          <input name="search" type="text" placeholder="Name, email, or username" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="form-group" style="margin:0;min-width:140px">
          <label>Role</label>
          <select name="role">
            <option value="">All roles</option>
            <option value="Administrator" <?= $role==='Administrator'?'selected':'' ?>>Administrator</option>
            <option value="Staff"         <?= $role==='Staff'        ?'selected':'' ?>>Staff</option>
          </select>
        </div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a href="users.php" class="btn">Clear</a>
      </form>
    </div>

    <!-- Users Table -->
    <div class="card">
      <h3>Staff Accounts</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Role</th>
              <th>Email</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($users): foreach ($users as $u):
              $isSelf  = (int)$u['id'] === (int)$_SESSION['admin_id'];
              $badge   = $u['status'] === 'Active' ? 'badge-success' : 'badge-danger';
            ?>
            <tr>
              <td><?= htmlspecialchars($u['name']) ?><?= $isSelf ? ' <span class="badge badge-info" style="font-size:10px">You</span>' : '' ?></td>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><span class="badge <?= $u['role']==='Administrator'?'badge-success':'badge-info' ?>"><?= htmlspecialchars($u['role']) ?></span></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($u['status']) ?></span></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <?php if (!$isSelf): ?>
                  <?php if ($u['status'] === 'Active'): ?>
                    <button class="btn btn-sm" style="background:#f97316;color:white;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:12px"
                      onclick="toggleStatus(<?= $u['id'] ?>,'Inactive',this)">Deactivate</button>
                  <?php else: ?>
                    <button class="btn btn-sm" style="background:#22c55e;color:white;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:12px"
                      onclick="toggleStatus(<?= $u['id'] ?>,'Active',this)">Activate</button>
                  <?php endif; ?>
                <?php endif; ?>
                <button class="btn btn-sm" style="background:#3b82f6;color:white;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:12px"
                  onclick="openResetPwd(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>')">Reset Pwd</button>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No users found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Add User Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;padding:28px;width:90%;max-width:460px">
    <h3 style="margin-top:0">Add New Staff User</h3>
    <div style="display:grid;gap:14px">
      <div class="form-group"><label>Full Name *</label><input type="text" id="uName" class="form-control" placeholder="e.g., Maria Santos"></div>
      <div class="form-group"><label>Username *</label><input type="text" id="uUsername" class="form-control" placeholder="e.g., msantos"></div>
      <div class="form-group"><label>Email *</label><input type="email" id="uEmail" class="form-control" placeholder="maria@example.com"></div>
      <div class="form-group">
        <label>Role</label>
        <select id="uRole" class="form-control">
          <option value="Staff">Staff</option>
          <option value="Administrator">Administrator</option>
        </select>
      </div>
      <div class="form-group"><label>Password * (min 6 chars)</label><input type="password" id="uPassword" class="form-control" placeholder="Password"></div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" style="flex:1" onclick="addUser()">Add User</button>
        <button class="btn btn-secondary" style="flex:1" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;padding:28px;width:90%;max-width:380px">
    <h3 style="margin-top:0">Reset Password — <span id="resetName"></span></h3>
    <div style="display:grid;gap:14px">
      <div class="form-group"><label>New Password *</label><input type="password" id="resetPwd" class="form-control" placeholder="New password"></div>
      <input type="hidden" id="resetUserId">
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" style="flex:1" onclick="saveResetPwd()">Update</button>
        <button class="btn btn-secondary" style="flex:1" onclick="document.getElementById('resetModal').style.display='none'">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
async function addUser() {
  const fd = new FormData();
  fd.append('action',   'add_user');
  fd.append('name',     document.getElementById('uName').value);
  fd.append('username', document.getElementById('uUsername').value);
  fd.append('email',    document.getElementById('uEmail').value);
  fd.append('role',     document.getElementById('uRole').value);
  fd.append('password', document.getElementById('uPassword').value);
  const res = await fetch('users.php', {method:'POST',body:fd});
  const d   = await res.json();
  if (d.success) { showToast('User added!','success'); setTimeout(()=>location.reload(),700); }
  else showToast(d.message,'error');
}

async function toggleStatus(id, status, btn) {
  const fd = new FormData();
  fd.append('action','toggle_status'); fd.append('id',id); fd.append('status',status);
  const res = await fetch('users.php',{method:'POST',body:fd});
  const d   = await res.json();
  if (d.success) { showToast(d.message,'success'); setTimeout(()=>location.reload(),700); }
  else showToast(d.message,'error');
}

function openResetPwd(id, name) {
  document.getElementById('resetUserId').value = id;
  document.getElementById('resetName').textContent = name;
  document.getElementById('resetPwd').value = '';
  document.getElementById('resetModal').style.display = 'flex';
}

async function saveResetPwd() {
  const fd = new FormData();
  fd.append('action',   'reset_password');
  fd.append('id',       document.getElementById('resetUserId').value);
  fd.append('password', document.getElementById('resetPwd').value);
  const res = await fetch('users.php',{method:'POST',body:fd});
  const d   = await res.json();
  if (d.success) { showToast('Password updated!','success'); document.getElementById('resetModal').style.display='none'; }
  else showToast(d.message,'error');
}
</script>
</body>
</html>
