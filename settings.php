<?php
// ============================================================
// settings.php  — Admin: Barangay settings management
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/site_settings.php';
requireAdminLogin();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update barangay info ───────────────────────────────────
    if ($action === 'update_info') {
        $fields = [
            'barangay_name'  => trim($_POST['barangay_name']  ?? ''),
            'address'        => trim($_POST['address']        ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'email'          => trim($_POST['email']          ?? ''),
        ];

        if ($fields['email'] && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!$fields['barangay_name']) {
            $error = 'Barangay name is required.';
        } else {
            foreach ($fields as $key => $val) {
                dbExecute(
                    "INSERT INTO barangay_settings (setting_key, setting_value)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = ?",
                    [$key, $val, $val]
                );
            }
            logActivity('Updated barangay settings', 'Settings');
            getSiteSettings(true);
            $success = 'Settings saved successfully!';
        }
    }

    // ── Change admin password ──────────────────────────────────
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $admin = dbFetchOne("SELECT * FROM admin_users WHERE id=?", [$_SESSION['admin_id']]);

        if (!password_verify($current, $admin['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            dbExecute(
                "UPDATE admin_users SET password_hash=? WHERE id=?",
                [password_hash($new, PASSWORD_BCRYPT), $_SESSION['admin_id']]
            );
            logActivity('Changed admin password', 'Settings');
            $success = 'Password updated successfully.';
        }
    }
}

$settings = getSiteSettings(true);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Settings — <?= getSiteName() ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Settings</h3>
        <div class="text-muted">Manage barangay information and account security.</div>
      </div>
    </div>

    <?php if ($success): ?>
      <div style="background:#dcfce7;color:#166534;padding:14px 18px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
        <span style="font-size:18px">✅</span> <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div style="background:#fee2e2;color:#991b1b;padding:14px 18px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
        <span style="font-size:18px">❌</span> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- ── Barangay Information ─────────────────────────────── -->
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_info">
      <div class="card">
        <h3>🏛️ Barangay Information</h3>
        <p class="text-muted" style="margin-bottom:18px">This information appears throughout the system including the resident portal office details.</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Barangay Name *</label>
            <input type="text" name="barangay_name" class="form-control"
              value="<?= htmlspecialchars($settings['barangay_name'] ?? 'Barangay San Isidro') ?>"
              required placeholder="e.g., Barangay San Isidro">
          </div>
          <div class="form-group">
            <label>Address</label>
            <input type="text" name="address" class="form-control"
              value="<?= htmlspecialchars($settings['address'] ?? '') ?>"
              placeholder="e.g., 123 Sampaguita St., Zone 2">
          </div>
          <div class="form-group">
            <label>Contact Number</label>
            <input type="text" name="contact_number" class="form-control"
              value="<?= htmlspecialchars($settings['contact_number'] ?? '') ?>"
              placeholder="e.g., (02) 555-1234">
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control"
              value="<?= htmlspecialchars($settings['email'] ?? '') ?>"
              placeholder="e.g., info@barangay.gov.ph">
          </div>
        </div>
        <button class="btn btn-primary" type="submit" style="margin-top:8px">💾 Save Information</button>
      </div>
    </form>

    <!-- ── Security / Password ─────────────────────────────── -->
    <form method="POST" style="margin-top:20px">
      <input type="hidden" name="action" value="change_password">
      <div class="card">
        <h3>🔒 Security Settings</h3>
        <p class="text-muted" style="margin-bottom:18px">Change your admin account password.</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Current Password</label>
            <div style="position:relative">
              <input type="password" name="current_password" id="curPwd" class="form-control" placeholder="Enter current password">
              <span onclick="togglePwd('curPwd',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:16px;user-select:none">👁️</span>
            </div>
          </div>
          <div class="form-group">
            <label>New Password</label>
            <div style="position:relative">
              <input type="password" name="new_password" id="newPwd" class="form-control" placeholder="Min. 8 characters"
                oninput="checkPwdStrength(this.value)">
              <span onclick="togglePwd('newPwd',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:16px;user-select:none">👁️</span>
            </div>
            <div style="margin-top:8px;height:4px;background:var(--border);border-radius:2px;overflow:hidden">
              <div id="pwdStrengthBar" style="height:100%;width:0%;background:#ef4444;border-radius:2px;transition:all 0.3s"></div>
            </div>
            <small id="pwdStrengthLabel" class="text-muted"></small>
          </div>
          <div class="form-group">
            <label>Confirm New Password</label>
            <div style="position:relative">
              <input type="password" name="confirm_password" id="conPwd" class="form-control" placeholder="Repeat new password">
              <span onclick="togglePwd('conPwd',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:16px;user-select:none">👁️</span>
            </div>
          </div>
        </div>
        <button class="btn btn-secondary" type="submit" style="margin-top:4px">🔒 Update Password</button>
      </div>
    </form>

  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
function togglePwd(id, icon) {
  const input = document.getElementById(id);
  if (input.type === 'password') { input.type = 'text'; icon.textContent = '🙈'; }
  else { input.type = 'password'; icon.textContent = '👁️'; }
}

function checkPwdStrength(val) {
  const bar   = document.getElementById('pwdStrengthBar');
  const label = document.getElementById('pwdStrengthLabel');
  let score = 0;
  if (val.length >= 8)           score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    { pct:'0%',   color:'#ef4444', text:'' },
    { pct:'25%',  color:'#ef4444', text:'Weak' },
    { pct:'50%',  color:'#f59e0b', text:'Fair' },
    { pct:'75%',  color:'#3b82f6', text:'Good' },
    { pct:'100%', color:'#10b981', text:'Strong' },
  ];
  const lvl = levels[score] ?? levels[0];
  bar.style.width      = lvl.pct;
  bar.style.background = lvl.color;
  label.textContent    = lvl.text;
  label.style.color    = lvl.color;
}
</script>
</body>
</html>
