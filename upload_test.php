<?php
// ============================================================
// upload_test.php — Temporary diagnostic page
// Delete this file after fixing the upload issue.
// ============================================================
session_start();
require_once 'includes/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$result = [];

// ── Test 1: Folder ────────────────────────────────────────────
$dir = __DIR__ . '/assets/images/print_templates/';
$result['folder_path']   = $dir;
$result['folder_exists'] = is_dir($dir) ? 'YES ✅' : 'NO ❌ — needs to be created';
$result['folder_writable'] = is_dir($dir) && is_writable($dir) ? 'YES ✅' : 'NO ❌ — no write permission';

// Try to create the folder if it doesn't exist
if (!is_dir($dir)) {
    $made = mkdir($dir, 0755, true);
    $result['folder_create_attempt'] = $made ? 'Created successfully ✅' : 'FAILED ❌ — create it manually in File Explorer';
}

// ── Test 2: DB table ──────────────────────────────────────────
try {
    $rows = dbFetchAll("SELECT document_type, image_path FROM document_templates");
    $result['db_table'] = 'EXISTS ✅ — ' . count($rows) . ' rows found';
    $result['db_rows']  = $rows;
} catch (Exception $e) {
    $result['db_table'] = 'ERROR ❌ — ' . $e->getMessage() . ' (did you run the SQL?)';
}

// ── Test 3: PHP upload settings ───────────────────────────────
$result['upload_max_filesize'] = ini_get('upload_max_filesize');
$result['post_max_size']       = ini_get('post_max_size');
$result['file_uploads']        = ini_get('file_uploads') ? 'ON ✅' : 'OFF ❌';

// ── Test 4: Handle actual test upload ────────────────────────
$uploadMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['testfile']['tmp_name'])) {
    $dest = $dir . 'test_upload.' . strtolower(pathinfo($_FILES['testfile']['name'], PATHINFO_EXTENSION));
    if (move_uploaded_file($_FILES['testfile']['tmp_name'], $dest)) {
        $uploadMessage = "✅ Upload worked! File saved to: $dest";
    } else {
        $uploadMessage = "❌ move_uploaded_file() failed. Error: " . json_encode(error_get_last());
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Upload Diagnostic</title>
  <style>
    body { font-family: monospace; padding: 30px; background: #f9fafb; }
    h2 { font-family: sans-serif; }
    .box { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
    table { border-collapse: collapse; width: 100%; }
    td { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
    td:first-child { font-weight: bold; color: #374151; width: 220px; }
    .ok { color: green; } .err { color: red; }
    .upload-result { padding: 14px; border-radius: 8px; margin-bottom: 16px;
      background: <?= $uploadMessage ? (str_contains($uploadMessage,'✅') ? '#dcfce7' : '#fee2e2') : '#fff' ?>;
      color: <?= $uploadMessage ? (str_contains($uploadMessage,'✅') ? '#166534' : '#991b1b') : '#111' ?>;
      border: 1px solid <?= $uploadMessage ? (str_contains($uploadMessage,'✅') ? '#86efac' : '#fca5a5') : '#e5e7eb' ?>; }
  </style>
</head>
<body>
<h2>🔍 Upload Diagnostic</h2>
<p style="color:#6b7280;font-family:sans-serif">This page tests whether uploads can work on your server. Delete it after fixing the issue.</p>

<?php if ($uploadMessage): ?>
<div class="upload-result"><?= htmlspecialchars($uploadMessage) ?></div>
<?php endif; ?>

<div class="box">
  <h3 style="font-family:sans-serif;margin:0 0 14px">System Checks</h3>
  <table>
    <tr><td>Folder path</td><td><?= htmlspecialchars($result['folder_path']) ?></td></tr>
    <tr><td>Folder exists</td><td><?= $result['folder_exists'] ?></td></tr>
    <tr><td>Folder writable</td><td><?= $result['folder_writable'] ?></td></tr>
    <?php if (isset($result['folder_create_attempt'])): ?>
    <tr><td>Folder creation</td><td><?= $result['folder_create_attempt'] ?></td></tr>
    <?php endif; ?>
    <tr><td>File uploads enabled</td><td><?= $result['file_uploads'] ?></td></tr>
    <tr><td>Max file size (php.ini)</td><td><?= $result['upload_max_filesize'] ?></td></tr>
    <tr><td>Max POST size (php.ini)</td><td><?= $result['post_max_size'] ?></td></tr>
    <tr><td>DB table</td><td><?= $result['db_table'] ?></td></tr>
  </table>
</div>

<?php if (!empty($result['db_rows'])): ?>
<div class="box">
  <h3 style="font-family:sans-serif;margin:0 0 14px">DB Rows in document_templates</h3>
  <table>
    <tr><td><strong>Document Type</strong></td><td><strong>Image Path</strong></td></tr>
    <?php foreach ($result['db_rows'] as $row): ?>
    <tr>
      <td><?= htmlspecialchars($row['document_type']) ?></td>
      <td><?= $row['image_path']
            ? (file_exists(__DIR__.'/'.$row['image_path'])
                ? '✅ ' . htmlspecialchars($row['image_path'])
                : '❌ Path saved but FILE NOT FOUND: ' . htmlspecialchars($row['image_path']))
            : '— not uploaded yet' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="box">
  <h3 style="font-family:sans-serif;margin:0 0 14px">Test Upload</h3>
  <p style="font-family:sans-serif;font-size:14px;margin-bottom:14px">Upload any image here to test if basic file saving works:</p>
  <form method="POST" enctype="multipart/form-data">
    <input type="file" name="testfile" accept="image/*" style="margin-bottom:10px;display:block">
    <button type="submit" style="padding:8px 18px;background:#0066cc;color:#fff;border:none;border-radius:7px;cursor:pointer;font-size:14px">
      Test Upload
    </button>
  </form>
</div>

</body>
</html>