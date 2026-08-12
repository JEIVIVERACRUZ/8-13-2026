<?php
// ============================================================
// reset_password.php  — Resident: Set new password via token
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/mailer.php';

$error   = '';
$success = '';
$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');

$reset = $token ? validatePasswordResetToken($token) : null;

if (!$reset) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        dbExecute(
            "UPDATE residents SET password_hash=? WHERE id=?",
            [password_hash($new, PASSWORD_BCRYPT), $reset['user_id']]
        );
        markResetTokenUsed($reset['id']);
        logActivity('Reset password via email link', 'Auth', 'resident');
        $success = 'Your password has been updated. You can now sign in.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reset Password — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-left">
      <div class="brand">
        <div class="logo"><img src="<?= getSiteLogo() ?>" alt="Logo"></div>
        <div>
          <h2>Local Assistance for Barangay Services</h2>
          <p class="text-muted">Barangay administration portal</p>
        </div>
      </div>
      <div style="margin-top:28px">
        <h3>Set a New Password</h3>
        <p>Choose a strong password you haven't used before.</p>
      </div>
    </div>
    <div class="login-right">
      <h3>Reset Password</h3>
      <?php if ($error): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:14px;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div style="background:#dcfce7;color:#166534;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:14px;">
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <?php if ($reset && !$success): ?>
      <form method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="form-group">
          <input class="form-control" name="new_password" type="password" placeholder="New password (min. 8 characters)" required>
        </div>
        <div class="form-group">
          <input class="form-control" name="confirm_password" type="password" placeholder="Confirm new password" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </form>
      <?php endif; ?>

      <?php if ($success || $error): ?>
      <div class="login-footer text-muted" style="margin-top:16px">
        <a href="index.php" style="color:var(--primary);font-weight:600;text-decoration:none">← Back to Sign In</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
