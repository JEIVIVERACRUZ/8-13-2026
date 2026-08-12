<?php
// ============================================================
// forgot_password.php  — Resident: Request password reset link
// ============================================================
session_start();
require_once 'includes/db.php';
require_once 'includes/mailer.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $resident = dbFetchOne(
            "SELECT id, first_name, last_name, email FROM residents WHERE email=? AND status!='Inactive' LIMIT 1",
            [$email]
        );

        // Always show the same success message, whether or not the email exists,
        // so attackers can't use this form to discover registered emails.
        if ($resident) {
            $token = createPasswordResetToken('resident', $resident['id']);
            $resetLink = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
                       . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;

            sendPasswordResetEmail(
                $resident['email'],
                $resident['first_name'] . ' ' . $resident['last_name'],
                $resetLink
            );
        }

        $success = 'If that email is registered, a password reset link has been sent. Please check your inbox.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Forgot Password — Barangay San Isidro</title>
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
        <h3>Forgot Your Password?</h3>
        <p>No worries — enter your email and we'll send you a reset link.</p>
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
      <?php if (!$success): ?>
      <form method="POST">
        <div class="form-group">
          <input class="form-control" name="email" type="email" placeholder="Your registered email" required>
        </div>
        <button type="submit" class="btn btn-primary">Send Reset Link</button>
      </form>
      <?php endif; ?>
      <div class="login-footer text-muted" style="margin-top:16px">
        <a href="index.php" style="color:var(--primary);font-weight:600;text-decoration:none">← Back to Sign In</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
