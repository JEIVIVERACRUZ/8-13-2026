<?php
// ============================================================
// announcements.php  — Admin: Manage announcements
// ============================================================
session_start();
require_once 'includes/db.php';
requireAdminLogin();

// ── AJAX handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $title    = trim($_POST['title']    ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'Other');
        $color    = trim($_POST['color']    ?? 'primary');
        $date     = trim($_POST['publish_date'] ?? date('Y-m-d'));

        if (!$title || !$desc) jsonError('Title and description are required.');

        dbInsert(
            "INSERT INTO announcements (title, description, category, color, publish_date, created_by)
             VALUES (?,?,?,?,?,?)",
            [$title, $desc, $category, $color, $date, $_SESSION['admin_id']]
        );
        logActivity("Created announcement: $title", 'Announcements');
        jsonOk([], 'Announcement published.');
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute("UPDATE announcements SET is_active=0 WHERE id=?", [$id]);
        logActivity("Deleted announcement #$id", 'Announcements');
        jsonOk([], 'Announcement removed.');
    }
    exit;
}

$announcements = dbFetchAll(
    "SELECT a.*, ad.name AS author
     FROM announcements a
     LEFT JOIN admin_users ad ON a.created_by = ad.id
     WHERE a.is_active = 1
     ORDER BY a.created_at DESC"
);

$colorMap = [
    'primary' => ['bg'=>'var(--primary-light)', 'border'=>'var(--primary)',  'text'=>'var(--primary)'],
    'success' => ['bg'=>'#dcfce7',              'border'=>'#22c55e',          'text'=>'#166534'],
    'warning' => ['bg'=>'#fef3c7',              'border'=>'#f59e0b',          'text'=>'#92400e'],
    'danger'  => ['bg'=>'#fee2e2',              'border'=>'#ef4444',          'text'=>'#991b1b'],
    'info'    => ['bg'=>'#dbeafe',              'border'=>'#3b82f6',          'text'=>'#1e40af'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Announcements — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Barangay Announcements</h3>
        <div class="text-muted">Create, manage, and publish barangay announcements for residents.</div>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('newForm').style.display='block';this.style.display='none'" id="newBtn">+ New Announcement</button>
      </div>
    </div>

    <!-- Create Form -->
    <div class="card" id="newForm" style="display:none;margin-bottom:24px">
      <h3>Create New Announcement</h3>
      <div class="form-grid">
        <div class="form-group" style="grid-column:1/-1">
          <label>Title *</label>
          <input type="text" id="aTitle" class="form-control" placeholder="Announcement title">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Description *</label>
          <textarea id="aDesc" class="form-control" placeholder="Announcement details" style="min-height:100px"></textarea>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select id="aCategory" class="form-control">
            <option value="Event">Event</option>
            <option value="Service Update">Service Update</option>
            <option value="Advisory">Advisory</option>
            <option value="Important Notice">Important Notice</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Color Theme</label>
          <select id="aColor" class="form-control">
            <option value="primary">Blue (Primary)</option>
            <option value="success">Green (Success)</option>
            <option value="warning">Yellow (Warning)</option>
            <option value="danger">Red (Danger)</option>
            <option value="info">Light Blue (Info)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Publish Date</label>
          <input type="date" id="aDate" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:16px">
        <button class="btn btn-primary btn-sm" onclick="publishAnnouncement()">Publish Announcement</button>
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('newForm').style.display='none';document.getElementById('newBtn').style.display='inline-flex'">Cancel</button>
      </div>
    </div>

    <!-- Active Announcements -->
    <div class="card">
      <h3>Active Announcements</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-top:16px" id="announcementsContainer">
        <?php if ($announcements): foreach ($announcements as $a):
          $c = $colorMap[$a['color']] ?? $colorMap['primary'];
          $dateStr = $a['publish_date'] ? date('M d, Y', strtotime($a['publish_date'])) : date('M d, Y', strtotime($a['created_at']));
        ?>
        <div style="background:<?= $c['bg'] ?>;border-left:4px solid <?= $c['border'] ?>;padding:16px;border-radius:8px" data-id="<?= $a['id'] ?>">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
              <h4 style="margin:0 0 8px;color:<?= $c['text'] ?>"><?= htmlspecialchars($a['title']) ?></h4>
              <p style="margin:0 0 6px;font-size:13px;color:var(--text-light)"><?= htmlspecialchars($a['description']) ?></p>
              <small style="color:var(--text-light)"><strong>Category:</strong> <?= htmlspecialchars($a['category']) ?> &nbsp;|&nbsp; <strong>Date:</strong> <?= $dateStr ?></small>
            </div>
            <button class="btn btn-secondary btn-sm" style="margin-left:12px;white-space:nowrap" onclick="deleteAnnouncement(<?= $a['id'] ?>, this)">Delete</button>
          </div>
        </div>
        <?php endforeach; else: ?>
        <p class="text-muted" style="grid-column:1/-1">No announcements yet. Create one above.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <h3>How This Works</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-top:16px">
        <div><h4>📝 Create Announcements</h4><p style="font-size:13px;color:var(--text-light)">Use the form above to create and publish announcements. Choose a category and color to make them stand out.</p></div>
        <div><h4>👀 Residents See Them</h4><p style="font-size:13px;color:var(--text-light)">All active announcements automatically appear on the resident portal home page for all residents to see.</p></div>
        <div><h4>🗑️ Delete When Done</h4><p style="font-size:13px;color:var(--text-light)">Remove announcements once they're no longer relevant. Click Delete to remove from the portal.</p></div>
      </div>
    </div>
  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
async function publishAnnouncement() {
  const title = document.getElementById('aTitle').value.trim();
  const desc  = document.getElementById('aDesc').value.trim();
  if (!title || !desc) { showToast('Title and description are required.','error'); return; }

  const fd = new FormData();
  fd.append('action',       'create');
  fd.append('title',        title);
  fd.append('description',  desc);
  fd.append('category',     document.getElementById('aCategory').value);
  fd.append('color',        document.getElementById('aColor').value);
  fd.append('publish_date', document.getElementById('aDate').value);

  const res  = await fetch('announcements.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.success) {
    showToast('Announcement published!', 'success');
    setTimeout(() => location.reload(), 800);
  } else {
    showToast(data.message, 'error');
  }
}

async function deleteAnnouncement(id, btn) {
  if (!confirm('Remove this announcement?')) return;
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('id', id);
  const res  = await fetch('announcements.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.success) {
    btn.closest('[data-id]').remove();
    showToast('Announcement removed.', 'info');
  }
}
</script>
</body>
</html>
