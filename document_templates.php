<?php
// ============================================================
// document_templates.php — Admin: Upload form images and
// position resident data fields on top of each template.
// ============================================================
session_start();
require_once 'includes/db.php';
requireAdminLogin();

error_reporting(E_ALL);
ini_set('display_errors', 0); // keep clean for users

$docTypes = [
    'Barangay Clearance',
    'Certificate of Residency',
    'Certificate of Indigency',
    'Other',
];

$success = '';
$error   = '';

// ── Handle regular form POST: image upload ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── Upload image (standard form POST) ────────────────────
    if ($_POST['action'] === 'upload_image') {
        $docType = trim($_POST['document_type'] ?? '');

        if (!$docType) {
            $error = 'Document type is missing.';
        } elseif (empty($_FILES['template']['tmp_name'])) {
            $error = 'No file received. Try again.';
        } else {
            $ext     = strtolower(pathinfo($_FILES['template']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            $maxSize = 5 * 1024 * 1024;

            if (!in_array($ext, $allowed)) {
                $error = 'Only JPG, PNG, or WebP images allowed.';
            } elseif ($_FILES['template']['size'] > $maxSize) {
                $error = 'File must be under 5MB.';
            } else {
                $dir = __DIR__ . '/assets/images/print_templates/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);

                $slug     = preg_replace('/[^a-z0-9]+/', '_', strtolower($docType));
                $filename = $slug . '.' . $ext;
                $fullPath = $dir . $filename;

                // Remove old image with different extension
                foreach ($allowed as $e) {
                    $old = $dir . $slug . '.' . $e;
                    if ($e !== $ext && file_exists($old)) @unlink($old);
                }

                if (move_uploaded_file($_FILES['template']['tmp_name'], $fullPath)) {
                    $relativePath = 'assets/images/print_templates/' . $filename;
                    dbExecute(
                        "UPDATE document_templates SET image_path=? WHERE document_type=?",
                        [$relativePath, $docType]
                    );
                    logActivity("Uploaded template image for: $docType", 'Document Templates');
                    $success = "Image uploaded successfully for: $docType";
                } else {
                    $error = 'Move failed. Check permissions on assets/images/print_templates/';
                }
            }
        }
    }

    // ── Save field positions (AJAX) ───────────────────────────
    if ($_POST['action'] === 'save_positions') {
        $docType   = trim($_POST['document_type'] ?? '');
        $positions = trim($_POST['positions']     ?? '');
        if (!$docType) jsonError('Document type is required.');
        $decoded = json_decode($positions, true);
        if (!is_array($decoded)) jsonError('Invalid position data.');
        dbExecute(
            "UPDATE document_templates SET field_positions=? WHERE document_type=?",
            [$positions, $docType]
        );
        logActivity("Updated field positions for: $docType", 'Document Templates');
        jsonOk([], 'Positions saved.');
    }
}

// Load all templates
$templates   = dbFetchAll("SELECT * FROM document_templates ORDER BY document_type");
$templateMap = array_column($templates, null, 'document_type');

// Scroll to the right card after upload
$scrollTo = isset($_POST['document_type']) ? preg_replace('/[^a-z0-9]+/', '_', strtolower($_POST['document_type'] ?? '')) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Document Templates — <?= getSiteName() ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
  <style>
    .template-card {
      border:1px solid var(--border);border-radius:12px;
      padding:20px;margin-bottom:24px;background:#fff
    }
    .preview-wrap {
      position:relative;display:block;
      border:2px dashed var(--border);border-radius:8px;
      overflow:hidden;background:#f3f4f6;
      min-height:220px;width:100%;cursor:crosshair;
    }
    .preview-wrap img { width:100%;display:block;pointer-events:none }
    .no-image {
      display:flex;align-items:center;justify-content:center;
      min-height:220px;color:var(--text-light);font-size:14px;
      flex-direction:column;gap:8px;
    }
    .field-pin {
      position:absolute;background:rgba(0,102,204,0.85);color:#fff;
      font-size:11px;padding:3px 7px;border-radius:4px;cursor:move;
      user-select:none;white-space:nowrap;z-index:10;
      border:1px solid rgba(255,255,255,0.5);
    }
    .fields-legend { display:flex;flex-wrap:wrap;gap:8px;margin-top:12px }
    .legend-item {
      display:flex;align-items:center;gap:6px;font-size:12px;
      background:var(--muted);border:1px solid var(--border);
      padding:5px 10px;border-radius:6px;cursor:pointer;
    }
    .legend-item:hover { background:var(--primary-light);border-color:var(--primary) }
    .legend-dot { width:10px;height:10px;border-radius:50%;background:#d1d5db }
    .legend-dot.placed { background:#10b981 }
    .upload-zone {
      border:2px dashed var(--border);border-radius:8px;padding:20px;
      text-align:center;background:var(--muted);margin-bottom:12px;
    }
  </style>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Document Templates</h3>
        <div class="text-muted">Upload a blank form image per document type, then drag fields to position them.</div>
      </div>
    </div>

    <?php if ($success): ?>
    <div style="background:#dcfce7;color:#166534;padding:14px 18px;border-radius:10px;margin-bottom:20px">
      ✅ <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:14px 18px;border-radius:10px;margin-bottom:20px">
      ❌ <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:20px">
      <h3>How to use</h3>
      <ol style="font-size:14px;color:var(--text-light);margin:8px 0;padding-left:20px;line-height:1.9">
        <li>Upload your blank form image under the correct document type</li>
        <li>Once the image appears, click a field label below it to place it on the image</li>
        <li>Drag each label to the exact blank line on the form</li>
        <li>Click <strong>Save Positions</strong></li>
      </ol>
    </div>

    <?php foreach ($docTypes as $docType):
      $tpl       = $templateMap[$docType] ?? [];
      $imagePath = $tpl['image_path'] ?? null;
      $positions = (!empty($tpl['field_positions'])) ? json_decode($tpl['field_positions'], true) : [];
      $slug      = preg_replace('/[^a-z0-9]+/', '_', strtolower($docType));
      $hasImage  = $imagePath && file_exists(__DIR__ . '/' . $imagePath);

      $availableFields = [
          'full_name'          => 'Full Name',
          'barangay_id'        => 'Barangay ID',
          'age'                => 'Age',
          'gender'             => 'Gender',
          'address'            => 'Full Address',
          'zone_purok'         => 'Zone / Purok',
          'date_of_birth'      => 'Date of Birth',
          'occupation'         => 'Occupation',
          'purpose'            => 'Purpose',
          'issue_date'         => 'Issue Date',
          'request_number'     => 'Request No.',
          'years_of_residency' => 'Years of Residency',
          'custom_text'        => 'Custom Text',
      ];
    ?>
    <div class="template-card" id="card-<?= $slug ?>">
      <h4 style="margin:0 0 16px;font-size:16px">
        📄 <?= htmlspecialchars($docType) ?>
        <?php if ($hasImage): ?>
          <span style="font-size:12px;font-weight:400;color:#10b981;margin-left:8px">✅ Image uploaded</span>
        <?php else: ?>
          <span style="font-size:12px;font-weight:400;color:#f59e0b;margin-left:8px">⚠️ No image yet</span>
        <?php endif; ?>
      </h4>

      <!-- ── Upload form (plain HTML form — no JS needed) ────── -->
      <form method="POST" enctype="multipart/form-data" style="margin-bottom:16px">
        <input type="hidden" name="action" value="upload_image">
        <input type="hidden" name="document_type" value="<?= htmlspecialchars($docType) ?>">
        <div class="upload-zone">
          <div style="font-size:28px;margin-bottom:6px">📤</div>
          <div style="font-size:14px;font-weight:600;margin-bottom:8px">
            <?= $hasImage ? 'Replace Form Image' : 'Upload Form Image' ?>
          </div>
          <div style="font-size:12px;color:var(--text-light);margin-bottom:12px">JPG, PNG, WebP · Max 5MB</div>
          <input type="file" name="template" accept="image/jpeg,image/png,image/webp"
                 required style="margin-bottom:10px;display:block;margin:0 auto 10px">
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px">
            Upload Image
          </button>
        </div>
      </form>

      <!-- ── Image preview + drag area ───────────────────────── -->
      <div class="preview-wrap" id="preview-<?= $slug ?>">
        <?php if ($hasImage): ?>
          <img src="<?= htmlspecialchars($imagePath) ?>?v=<?= filemtime(__DIR__ . '/' . $imagePath) ?>"
               id="img-<?= $slug ?>" alt="Template for <?= htmlspecialchars($docType) ?>">
        <?php else: ?>
          <div class="no-image">
            <span style="font-size:32px">🖼️</span>
            <span>Upload an image above to see the preview here</span>
          </div>
        <?php endif; ?>

        <!-- Render saved field pins -->
        <?php foreach ($positions as $field => $pos):
          if (!isset($availableFields[$field])) continue;
          $fs   = (int)($pos['font_size'] ?? 12);
          $bold = !empty($pos['bold']) ? 'font-weight:bold;' : '';
        ?>
        <div class="field-pin"
             id="pin-<?= $slug ?>-<?= $field ?>"
             data-field="<?= $field ?>"
             data-slug="<?= $slug ?>"
             style="top:<?= (float)$pos['top'] ?>%;left:<?= (float)$pos['left'] ?>%;font-size:<?= $fs ?>px;<?= $bold ?>"
             title="Drag to reposition">
          <?= htmlspecialchars($availableFields[$field]) ?>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($hasImage): ?>
      <!-- ── Field labels ─────────────────────────────────────── -->
      <div style="margin-top:10px;font-size:13px;font-weight:600;color:var(--text-light)">
        Click a field to place it on the image, then drag it into position:
      </div>
      <div class="fields-legend" id="legend-<?= $slug ?>">
        <?php foreach ($availableFields as $key => $label): ?>
        <div class="legend-item" id="leg-<?= $slug ?>-<?= $key ?>"
             onclick="togglePin('<?= $slug ?>', '<?= $key ?>', '<?= addslashes($label) ?>')">
          <div class="legend-dot <?= isset($positions[$key]) ? 'placed' : '' ?>"
               id="dot-<?= $slug ?>-<?= $key ?>"></div>
          <?= htmlspecialchars($label) ?>
        </div>
        <?php endforeach; ?>

        <!-- Render existing saved custom_text_N fields as legend items -->
        <?php foreach ($positions as $key => $pos):
          if (!preg_match('/^custom_text_\d+$/', $key)) continue;
          $num = str_replace('custom_text_', '', $key);
        ?>
        <div class="legend-item" id="leg-<?= $slug ?>-<?= $key ?>"
             style="border-color:#f59e0b;background:#fef3c7"
             onclick="togglePin('<?= $slug ?>', '<?= $key ?>', 'Custom Text <?= $num ?>')">
          <div class="legend-dot placed" id="dot-<?= $slug ?>-<?= $key ?>"
               style="background:#f59e0b"></div>
          Custom Text <?= $num ?>
        </div>
        <?php endforeach; ?>

        <!-- Button stays LAST inside the flex container so it never gets pushed out -->
        <button id="addcustom-<?= $slug ?>"
                style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:7px;cursor:pointer;padding:5px 12px;font-size:12px;font-weight:600;white-space:nowrap"
                onclick="addCustomTextField('<?= $slug ?>', '<?= addslashes($docType) ?>')">
          + Add Custom Text
        </button>
      </div>

      <!-- ── Font controls ────────────────────────────────────── -->
      <div style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px">
        <span style="color:var(--text-light)">Selected field:</span>
        <span id="selectedLabel-<?= $slug ?>" style="color:var(--primary);font-weight:600;min-width:80px">None</span>
        <span style="color:var(--text-light)">Font size:</span>
        <input type="number" id="fontSize-<?= $slug ?>" value="12" min="8" max="48"
               style="width:60px;padding:4px 8px;border:1px solid var(--border);border-radius:6px"
               oninput="updateFontSize('<?= $slug ?>', this.value)">
        <label style="display:flex;align-items:center;gap:5px">
          <input type="checkbox" id="bold-<?= $slug ?>"
                 onchange="updateBold('<?= $slug ?>', this.checked)"> Bold
        </label>
      </div>

      <!-- ── Save positions button ─────────────────────────────── -->
      <div style="margin-top:14px;display:flex;gap:10px">
        <button class="btn btn-primary btn-sm" onclick="savePositions('<?= $slug ?>', '<?= addslashes($docType) ?>')">
          💾 Save Positions
        </button>
        <button class="btn btn-secondary btn-sm" onclick="clearAll('<?= $slug ?>')">
          🗑️ Clear All
        </button>
      </div>
      <?php else: ?>
      <p style="color:var(--text-light);font-size:13px;margin-top:10px">
        Upload an image first to enable field positioning.
      </p>
      <?php endif; ?>

    </div><!-- end template-card -->
    <?php endforeach; ?>

  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
const selectedPin = {};
const pinData     = {};

// ── Add a new custom_text_N field dynamically ─────────────────
function addCustomTextField(slug, docType) {
  if (!pinData[slug]) pinData[slug] = {};

  // Find next available number (custom_text_1, custom_text_2, ...)
  let num = 1;
  while (pinData[slug]['custom_text_' + num]) num++;
  const key   = 'custom_text_' + num;
  const label = 'Custom Text ' + num;

  const wrap = document.getElementById('preview-' + slug);
  if (!document.getElementById('img-' + slug)) {
    showToast('Upload an image first.', 'error'); return;
  }

  // Create the pin on the image
  createPin(slug, key, label, 45 + (num * 3), 20, 12, false);
  selectPin(slug, key);

  // Add a legend item for it — insert BEFORE the button so button stays last
  const legend = document.getElementById('legend-' + slug);
  const btn    = document.getElementById('addcustom-' + slug);
  const item   = document.createElement('div');
  item.className = 'legend-item';
  item.id        = 'leg-' + slug + '-' + key;
  item.style.borderColor = '#f59e0b';
  item.style.background  = '#fef3c7';
  item.innerHTML = `<div class="legend-dot placed" id="dot-${slug}-${key}" style="background:#f59e0b"></div> ${label}`;
  item.onclick   = () => togglePin(slug, key, label);
  legend.insertBefore(item, btn); // ← always keeps button at the end

  showToast(`"${label}" added — drag it into position.`, 'info');
}

// ── Toggle pin on/off ─────────────────────────────────────────
function togglePin(slug, field, label) {
  if (!pinData[slug]) pinData[slug] = {};
  if (pinData[slug][field]) {
    pinData[slug][field].el.remove();
    delete pinData[slug][field];
    selectedPin[slug] = null;
    document.getElementById('selectedLabel-' + slug).textContent = 'None';
    const dot = document.getElementById('dot-' + slug + '-' + field);
    if (dot) dot.classList.remove('placed');
    return;
  }
  const wrap = document.getElementById('preview-' + slug);
  if (!document.getElementById('img-' + slug)) {
    showToast('Upload an image first.', 'error'); return;
  }
  createPin(slug, field, label, 45, 20, 12, false);
  selectPin(slug, field);
}

function createPin(slug, field, label, top, left, fontSize, bold) {
  if (!pinData[slug]) pinData[slug] = {};
  const wrap = document.getElementById('preview-' + slug);
  const pin  = document.createElement('div');
  pin.className      = 'field-pin';
  pin.id             = 'pin-' + slug + '-' + field;
  pin.dataset.field  = field;
  pin.dataset.slug   = slug;
  pin.textContent    = label;
  pin.style.top      = top  + '%';
  pin.style.left     = left + '%';
  pin.style.fontSize = fontSize + 'px';
  pin.style.fontWeight = bold ? 'bold' : 'normal';
  makeDraggable(pin, wrap);
  pin.addEventListener('click', (e) => { e.stopPropagation(); selectPin(slug, field); });
  wrap.appendChild(pin);
  pinData[slug][field] = { top, left, font_size: fontSize, bold, el: pin, label };
  const dot = document.getElementById('dot-' + slug + '-' + field);
  if (dot) dot.classList.add('placed');
}

function selectPin(slug, field) {
  selectedPin[slug] = field;
  const d = pinData[slug]?.[field];
  if (!d) return;
  document.getElementById('fontSize-'     + slug).value   = d.font_size || 12;
  document.getElementById('bold-'         + slug).checked = !!d.bold;
  document.getElementById('selectedLabel-'+ slug).textContent = d.label;
}

function updateFontSize(slug, size) {
  const field = selectedPin[slug];
  if (!field || !pinData[slug]?.[field]) return;
  pinData[slug][field].font_size      = parseInt(size);
  pinData[slug][field].el.style.fontSize = size + 'px';
}

function updateBold(slug, bold) {
  const field = selectedPin[slug];
  if (!field || !pinData[slug]?.[field]) return;
  pinData[slug][field].bold = bold;
  pinData[slug][field].el.style.fontWeight = bold ? 'bold' : 'normal';
}

function clearAll(slug) {
  if (!pinData[slug]) return;
  Object.values(pinData[slug]).forEach(d => d.el.remove());
  pinData[slug] = {};
  selectedPin[slug] = null;
  document.getElementById('selectedLabel-' + slug).textContent = 'None';
  document.querySelectorAll('#legend-' + slug + ' .legend-dot').forEach(d => d.classList.remove('placed'));
}

// ── Drag ──────────────────────────────────────────────────────
function makeDraggable(el, container) {
  let startX, startY, startLeft, startTop;
  el.addEventListener('mousedown', (e) => {
    e.preventDefault();
    selectPin(el.dataset.slug, el.dataset.field);
    const rect = container.getBoundingClientRect();
    startX = e.clientX; startY = e.clientY;
    startLeft = parseFloat(el.style.left);
    startTop  = parseFloat(el.style.top);
    const onMove = (e) => {
      const dx   = ((e.clientX - startX) / rect.width)  * 100;
      const dy   = ((e.clientY - startY) / rect.height) * 100;
      const newL = Math.min(95, Math.max(0, startLeft + dx));
      const newT = Math.min(97, Math.max(0, startTop  + dy));
      el.style.left = newL + '%';
      el.style.top  = newT + '%';
      if (pinData[el.dataset.slug]?.[el.dataset.field]) {
        pinData[el.dataset.slug][el.dataset.field].left = newL;
        pinData[el.dataset.slug][el.dataset.field].top  = newT;
      }
    };
    const onUp = () => {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup',   onUp);
    };
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup',   onUp);
  });
}

// ── Save positions ────────────────────────────────────────────
async function savePositions(slug, docType) {
  const pins = pinData[slug] || {};
  if (Object.keys(pins).length === 0) {
    showToast('No fields placed yet. Click a field label to place it.', 'error');
    return;
  }
  const payload = {};
  Object.entries(pins).forEach(([field, d]) => {
    payload[field] = { top: d.top, left: d.left, font_size: d.font_size || 12, bold: !!d.bold };
  });
  const fd = new FormData();
  fd.append('action',        'save_positions');
  fd.append('document_type', docType);
  fd.append('positions',     JSON.stringify(payload));
  const res  = await fetch('document_templates.php', { method:'POST', body:fd });
  const data = await res.json();
  showToast(data.message, data.success ? 'success' : 'error');
}

// ── Init existing pins on page load ───────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.field-pin').forEach(pin => {
    const slug  = pin.dataset.slug;
    const field = pin.dataset.field;
    if (!pinData[slug]) pinData[slug] = {};
    const wrap = document.getElementById('preview-' + slug);
    pinData[slug][field] = {
      top:       parseFloat(pin.style.top),
      left:      parseFloat(pin.style.left),
      font_size: parseInt(pin.style.fontSize) || 12,
      bold:      pin.style.fontWeight === 'bold',
      el:        pin,
      label:     pin.textContent.trim(),
    };
    makeDraggable(pin, wrap);
    pin.addEventListener('click', (e) => { e.stopPropagation(); selectPin(slug, field); });
  });

  // Scroll to the card that was just uploaded
  const scrollTo = '<?= $scrollTo ?>';
  if (scrollTo) {
    const card = document.getElementById('card-' + scrollTo);
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
});
</script>
</body>
</html>