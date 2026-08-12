<?php
// ============================================================
// signup.php  — Resident registration
// ============================================================
session_start();
require_once 'includes/db.php';

if (!empty($_SESSION['resident_id'])) {
    header('Location: resident_portal.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect & sanitize
    $firstName   = trim($_POST['first_name']   ?? '');
    $lastName    = trim($_POST['last_name']    ?? '');
    $email       = trim($_POST['email']        ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $dob         = trim($_POST['dob']          ?? '');
    $gender      = trim($_POST['gender']       ?? '');
    $street      = trim($_POST['street']       ?? '');
    $zone        = trim($_POST['zone']         ?? '');
    $house       = trim($_POST['house_number'] ?? '');
    $occupation  = trim($_POST['occupation']   ?? '');
    $password    = $_POST['password']          ?? '';
    $confirm     = $_POST['confirm_password']  ?? '';

    // Validate
    if (!$firstName || !$lastName || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check email uniqueness
        $exists = dbFetchOne('SELECT id FROM residents WHERE email=? LIMIT 1', [$email]);
        if ($exists) {
            $error = 'An account with that email already exists.';
        } else {
            try {
                $barangayId   = generateResidentId();
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                $residentId = dbInsert(
                    "INSERT INTO residents
                       (barangay_id, first_name, last_name, email, phone,
                        date_of_birth, gender, street_address, zone_purok, house_number,
                        occupation, password_hash, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Pending')",
                    [
                        $barangayId, $firstName, $lastName, $email, $phone,
                        $dob ?: null, $gender, $street, $zone, $house,
                        $occupation, $passwordHash,
                    ]
                );

                logActivity("New resident registered: $barangayId", 'Signup', 'resident');
                $success = "Account created! Your Barangay ID is <strong>$barangayId</strong>. You can now <a href='index.php'>sign in</a>.";

            } catch (Exception $e) {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign Up — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="login-wrap">
  <div class="login-card" style="width:920px">
    <div class="login-left">
      <div class="brand">
        <div class="logo"><img src="<?= getSiteLogo() ?>" alt="Logo"></div>
        <div>
          <h2>Barangay San Isidro</h2>
          <p class="text-muted">Resident Registration Portal</p>
        </div>
      </div>
      <div style="margin-top:28px">
        <h3>Welcome to Our Community</h3>
        <p>Register as a resident to access barangay services, request documents, and stay connected with your community.</p>
      </div>
    </div>
    <div class="login-right" style="max-height:90vh;overflow-y:auto;justify-content:flex-start !important">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h3>Create Account</h3>
        <a href="index.php" class="text-muted" style="font-size:13px;text-decoration:none">Back to Login</a>
      </div>

      <?php if ($error): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:14px;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div style="background:#dcfce7;color:#166534;padding:14px;border-radius:8px;margin-bottom:16px;font-size:14px;">
          <?= $success ?>
        </div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST" id="signupForm">
        <div class="form-grid">
          <div class="form-group">
            <label>First Name *</label>
            <input class="form-control" type="text" name="first_name" placeholder="Juan" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Last Name *</label>
            <input class="form-control" type="text" name="last_name" placeholder="Dela Cruz" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Email Address *</label>
            <input class="form-control" type="email" name="email" placeholder="juan@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input class="form-control" type="tel" name="phone" placeholder="0917 555 0123" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Date of Birth</label>
            <input class="form-control" type="date" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Gender</label>
            <select class="form-control" name="gender">
              <option value="">Select...</option>
              <option value="Male"   <?= (($_POST['gender'] ?? '') === 'Male'   ? 'selected' : '') ?>>Male</option>
              <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female' ? 'selected' : '') ?>>Female</option>
              <option value="Other"  <?= (($_POST['gender'] ?? '') === 'Other'  ? 'selected' : '') ?>>Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Street Address</label>
          <input class="form-control" type="text" name="street" placeholder="123 Sampaguita St." value="<?= htmlspecialchars($_POST['street'] ?? '') ?>">
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Zone / Purok</label>
            <select class="form-control" name="zone">
              <option value="">Select Zone...</option>
              <?php
              $puroks = dbFetchAll("SELECT name FROM puroks ORDER BY name");
              if ($puroks) {
                  foreach ($puroks as $p) {
                      $sel = (($_POST['zone'] ?? '') === $p['name']) ? 'selected' : '';
                      echo "<option value=\"".htmlspecialchars($p['name'])."\" $sel>".htmlspecialchars($p['name'])."</option>";
                  }
              } else {
                  // fallback static options
                  foreach (['Zone 1','Zone 2','Zone 3','Zone 4'] as $z) {
                      $sel = (($_POST['zone'] ?? '') === $z) ? 'selected' : '';
                      echo "<option value=\"$z\" $sel>$z</option>";
                  }
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label>House Number</label>
            <input class="form-control" type="text" name="house_number" placeholder="Lot 5" value="<?= htmlspecialchars($_POST['house_number'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Occupation</label>
          <input class="form-control" type="text" name="occupation" placeholder="e.g., Teacher, Farmer" value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>">
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Password *</label>
            <input class="form-control" type="password" name="password" placeholder="Min. 8 characters" required>
          </div>
          <div class="form-group">
            <label>Confirm Password *</label>
            <input class="form-control" type="password" name="confirm_password" placeholder="Re-enter password" required>
          </div>
        </div>
        <div style="margin:16px 0;display:flex;align-items:flex-start;gap:8px">
          <input type="checkbox" id="agreeTerms" required style="margin-top:2px">
          <label for="agreeTerms" style="font-size:13px;line-height:1.4">I agree to the Terms of Service and Privacy Policy of Barangay San Isidro and confirm that the information provided is accurate.</label>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%">Create Account</button>
        <div class="login-footer text-muted" style="margin-top:16px">
          Already have an account? <a href="index.php" style="color:var(--primary);font-weight:600;text-decoration:none">Sign In</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
