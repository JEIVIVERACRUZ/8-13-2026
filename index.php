<?php
// ============================================================
// index.php  — Resident login
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/site_settings.php';

if (!empty($_SESSION['resident_id'])) {
    header('Location: resident_portal.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your credentials.';
    } else {
        $resident = dbFetchOne(
            "SELECT * FROM residents WHERE (email=? OR barangay_id=?) AND status!='Inactive' LIMIT 1",
            [$username, $username]
        );
        if ($resident && password_verify($password, $resident['password_hash'])) {
    $_SESSION['resident_id']        = $resident['id'];
    $_SESSION['resident_barangay_id'] = $resident['barangay_id'];
    $_SESSION['resident_name']      = $resident['first_name'] . ' ' . $resident['last_name'];
    if (!empty($_POST['remember'])) {
        issueRememberToken('resident', $resident['id']);
    }
    logActivity('Resident login', 'Auth', 'resident');
    header('Location: resident_portal.php');
    exit;
} else {
    $error = 'Invalid credentials.';
}
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ALAB-SI — Login</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-left">
      <div class="brand">
        <div class="logo"><img src="<?= getSiteLogo() ?>" alt="ALAB-SI Logo"></div>
        <div>
          <h2>Local Assistance for Barangay Services</h2>
          <p class="text-muted">Barangay administration portal</p>
        </div>
      </div>
      <div style="margin-top:28px">
        <h3>Welcome Back!</h3>
        <p>Sign in to continue to ALAB-SI</p>
      </div>
    </div>
    <div class="login-right">
      <h3>Sign In</h3>
      <?php if ($error): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:14px;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <input class="form-control" name="username" placeholder="Email or Barangay ID" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <div style="position:relative">
            <input class="form-control" name="password" id="loginPwd" placeholder="Password" type="password" required style="padding-right:44px">
            <span onclick="togglePwd('loginPwd', this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;user-select:none;display:flex;color:#6b7280">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
          </div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <label class="remember"><input type="checkbox" name="remember" value="1" checked> Remember me</label>
          <a class="text-muted" href="forgot_password.php">Forgot Password</a>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
          <button type="submit" class="btn btn-primary">Login</button>
        </div>
        <div class="login-footer text-muted">
          Don't have an account? <a href="signup.php" style="color:var(--primary);font-weight:600;text-decoration:none">Sign Up Here</a>
        </div>
        <div style="text-align:center;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
          <small class="text-muted">Staff/Admin? <a href="admin_login.php" style="color:var(--primary);font-weight:600;text-decoration:none">Admin Login</a></small>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="assets/js/app.js"></script>
<script>
const EYE_OPEN  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
const EYE_OFF   = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.6 18.6 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
function togglePwd(id, icon) {
  const input = document.getElementById(id);
  if (input.type === 'password') {
    input.type = 'text';
    icon.innerHTML = EYE_OFF;
  } else {
    input.type = 'password';
    icon.innerHTML = EYE_OPEN;
  }
}
</script>
</body>
</html>