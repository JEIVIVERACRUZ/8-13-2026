<?php
// path_check.php — delete after fixing
session_start();
require_once 'includes/db.php';

$rows = dbFetchAll("SELECT document_type, image_path FROM document_templates WHERE image_path IS NOT NULL");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Path Check</title>
  <style>
    body { font-family: monospace; padding: 30px; background: #f9fafb; }
    .box { background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:20px }
    td { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; font-size:13px; vertical-align:top }
    td:first-child { font-weight:bold; color:#374151; width:200px }
  </style>
</head>
<body>
<h2>🔍 Path Check</h2>

<?php if (!$rows): ?>
  <p style="color:red">❌ No image paths found in DB yet — upload an image in Document Templates first, then come back here.</p>
<?php endif; ?>

<?php foreach ($rows as $row): ?>
<div class="box">
  <h3 style="margin:0 0 14px"><?= htmlspecialchars($row['document_type']) ?></h3>
  <table>
    <tr>
      <td>Raw DB value</td>
      <td><code><?= htmlspecialchars($row['image_path']) ?></code></td>
    </tr>
    <tr>
      <td>File exists on disk?</td>
      <td><?= file_exists(__DIR__ . '/' . $row['image_path']) ? '✅ Yes' : '❌ No — path is wrong' ?></td>
    </tr>
    <tr>
      <td>URL to test in browser</td>
      <td>
        <a href="<?= htmlspecialchars($row['image_path']) ?>" target="_blank">
          <?= htmlspecialchars($row['image_path']) ?>
        </a>
      </td>
    </tr>
    <tr>
      <td>Image preview</td>
      <td>
        <img src="<?= htmlspecialchars($row['image_path']) ?>"
             style="max-width:300px;border:2px solid #e5e7eb;border-radius:6px;margin-top:6px"
             onerror="this.style.display='none';this.nextSibling.style.display='block'">
        <span style="display:none;color:red">❌ Image failed to load — URL is broken</span>
      </td>
    </tr>
  </table>
</div>
<?php endforeach; ?>

</body>
</html>