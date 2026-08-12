<?php
// includes/head_meta.php
// Usage: include this INSIDE <head> on every page, AFTER loading db.php.
// It outputs the theme <style> override so --primary reflects DB settings.
// $pageTitle should be set before including this file.
$pageTitle = $pageTitle ?? 'Barangay San Isidro';
?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= getSiteName() ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
