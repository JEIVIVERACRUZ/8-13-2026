<?php
// ============================================================
// mapping.php  — Admin: Geographic mapping + purok management
// ============================================================
session_start();
require_once 'includes/db.php';
requireAdminLogin();

// ── AJAX handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_purok') {
        $name   = trim($_POST['name']        ?? '');
        $color  = trim($_POST['color']       ?? '#34d399');
        $coords = trim($_POST['coordinates'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $type   = trim($_POST['type']        ?? 'zone');

        if (!$name) jsonError('Name is required.');
        if (!in_array($type, ['zone','marker'])) $type = 'zone';
        if (!$coords || $coords === '[]' || $coords === '{}') {
            jsonError('Please draw a marker or shape on the map before saving.');
        }

        dbInsert(
            "INSERT INTO puroks (name, type, description, color, coordinates) VALUES (?,?,?,?,?)",
            [$name, $type, $desc, $color, $coords]
        );
        $label = $type === 'marker' ? 'Marker' : 'Purok';
        logActivity("Saved $label: $name", 'Mapping');
        jsonOk([], "$label '$name' saved.");
    }
    if ($_POST['action'] === 'delete_purok') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute("DELETE FROM puroks WHERE id=?", [$id]);
        logActivity("Deleted map item #$id", 'Mapping');
        jsonOk([], 'Deleted.');
    }
    exit;
}

$puroks     = dbFetchAll("SELECT * FROM puroks ORDER BY created_at DESC");
$zoneList   = array_values(array_filter($puroks, fn($p) => ($p['type'] ?? 'zone') === 'zone'));
$markerList = array_values(array_filter($puroks, fn($p) => ($p['type'] ?? 'zone') === 'marker'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Geographic Mapping — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
  <style>
    #map { height:560px;border-radius:10px;margin-top:16px;box-shadow:0 1px 3px rgba(0,0,0,.1) }
    .purok-item { display:flex;justify-content:space-between;align-items:center;padding:12px;margin:8px 0;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb }
    .purok-name { font-weight:600;color:#1f2937 }
    .purok-color { width:22px;height:22px;border-radius:4px;border:2px solid #e5e7eb;flex-shrink:0 }
    .purok-color.round { border-radius:50% }
    .type-tag { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:2px 8px;border-radius:10px;margin-left:8px }
    .type-tag.zone   { background:#dcfce7;color:#166534 }
    .type-tag.marker { background:#dbeafe;color:#1e40af }
    .draw-mode-toggle { display:flex;gap:8px;margin-bottom:12px }
    .draw-mode-toggle button { flex:1;padding:10px;border-radius:8px;border:2px solid var(--border);background:#fff;cursor:pointer;font-weight:600;font-size:13px }
    .draw-mode-toggle button.active { border-color:var(--primary);background:var(--primary-light);color:var(--primary) }
  </style>
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Geographic Mapping</h3>
        <div class="text-muted">Draw zone/purok boundaries or drop markers for points of interest.</div>
      </div>
    </div>

    <div class="card">
      <h3>Interactive Map &amp; Purok Management</h3>
      <p class="text-muted">Choose whether you're drawing a <strong>Zone/Purok</strong> (shown to residents) or a <strong>Marker</strong> (admin-only reference point), then draw on the map.</p>
      <div class="form-grid" style="margin-top:16px">
        <div class="form-group">
          <label>Search Resident</label>
          <input type="text" id="residentSearch" placeholder="Search by name or address">
        </div>
        <div class="form-group">
          <label>Purok / Zone Filter</label>
          <select id="zoneFilter" onchange="filterByPurok(this.value)">
            <option value="">All Puroks</option>
            <?php foreach ($zoneList as $p): ?>
              <option value="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div id="map"></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
      <!-- Drawing tools -->
      <div class="card">
        <h3>Drawing Tools</h3>
        <p class="text-muted">Pick a type, draw on the map, fill in the details, then save.</p>

        <div class="draw-mode-toggle" style="margin-top:16px">
          <button type="button" id="modeZoneBtn" class="active" onclick="setDrawMode('zone')">🗺️ Zone / Purok</button>
          <button type="button" id="modeMarkerBtn" onclick="setDrawMode('marker')">📍 Marker</button>
        </div>

        <div style="display:grid;gap:12px">
          <div class="form-group">
            <label>Name *</label>
            <input type="text" id="purokName" class="form-control" placeholder="e.g., Purok Mabuhay or Barangay Hall">
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" id="purokDesc" class="form-control" placeholder="Optional description">
          </div>
          <div class="form-group">
            <label>Color</label>
            <input type="color" id="purokColor" value="#34d399">
          </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-primary" onclick="savePurok()">💾 Save</button>
          <button class="btn" onclick="clearDrawing()" style="margin-left:0">🗑️ Clear Drawing</button>
        </div>
        <div style="margin-top:12px;padding:12px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;font-size:13px;color:#666">
          <strong>How to use:</strong>
          <ul style="margin:8px 0;padding-left:20px">
            <li><strong>Zone / Purok</strong> mode: use the polygon or rectangle tool. This is what residents see.</li>
            <li><strong>Marker</strong> mode: use the marker (pin) tool for a single point. Markers are admin-only and are not shown to residents.</li>
            <li>Fill in the name above and click Save.</li>
          </ul>
        </div>
      </div>

      <!-- Saved items list -->
      <div class="card">
        <h3>Saved Puroks &amp; Markers</h3>
        <p class="text-muted">Zones/Puroks are visible to residents. Markers are admin-only.</p>

        <div style="margin-top:16px;font-size:13px;font-weight:700;color:#166534">🗺️ Zones / Puroks (resident-visible)</div>
        <div id="puroksList" style="margin-top:8px;max-height:220px;overflow-y:auto">
          <?php if ($zoneList): foreach ($zoneList as $p): ?>
          <div class="purok-item" data-id="<?= $p['id'] ?>" data-type="zone" data-coords='<?= htmlspecialchars($p['coordinates'] ?? '[]') ?>'>
            <div>
              <div class="purok-name"><?= htmlspecialchars($p['name']) ?><span class="type-tag zone">Zone</span></div>
              <small class="text-muted"><?= htmlspecialchars($p['description'] ?? 'No description') ?></small>
            </div>
            <div class="purok-color" style="background:<?= htmlspecialchars($p['color']) ?>"></div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-sm" style="padding:4px 8px;font-size:12px" onclick="focusPurok(<?= $p['id'] ?>)">Focus</button>
              <button class="btn btn-sm" style="padding:4px 8px;font-size:12px;background:#fee2e2;color:#991b1b;border-color:#fca5a5" onclick="deletePurok(<?= $p['id'] ?>, this)">Delete</button>
            </div>
          </div>
          <?php endforeach; else: ?>
          <p class="text-muted" style="padding:8px 0;font-size:13px">No zones saved yet.</p>
          <?php endif; ?>
        </div>

        <div style="margin-top:20px;font-size:13px;font-weight:700;color:#1e40af">📍 Markers (admin-only)</div>
        <div id="markersList" style="margin-top:8px;max-height:220px;overflow-y:auto">
          <?php if ($markerList): foreach ($markerList as $p): ?>
          <div class="purok-item" data-id="<?= $p['id'] ?>" data-type="marker" data-coords='<?= htmlspecialchars($p['coordinates'] ?? '{}') ?>'>
            <div>
              <div class="purok-name"><?= htmlspecialchars($p['name']) ?><span class="type-tag marker">Marker</span></div>
              <small class="text-muted"><?= htmlspecialchars($p['description'] ?? 'No description') ?></small>
            </div>
            <div class="purok-color round" style="background:<?= htmlspecialchars($p['color']) ?>"></div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-sm" style="padding:4px 8px;font-size:12px" onclick="focusPurok(<?= $p['id'] ?>)">Focus</button>
              <button class="btn btn-sm" style="padding:4px 8px;font-size:12px;background:#fee2e2;color:#991b1b;border-color:#fca5a5" onclick="deletePurok(<?= $p['id'] ?>, this)">Delete</button>
            </div>
          </div>
          <?php endforeach; else: ?>
          <p class="text-muted" style="padding:8px 0;font-size:13px">No markers saved yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Purok data for JS -->
<script>
const savedPuroks = <?= json_encode($puroks) ?>;
let currentDrawMode = 'zone'; // 'zone' or 'marker'
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="assets/js/app.js"></script>
<script>
let mapInstance, drawnItems, lastDrawnLayer = null;
let drawControl, zonePolygonHandler, zoneRectHandler, markerHandler;

document.addEventListener('DOMContentLoaded', function() {
  mapInstance = L.map('map').setView([14.1153, 121.1476], 15);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
  }).addTo(mapInstance);

  drawnItems = new L.FeatureGroup().addTo(mapInstance);

  drawControl = new L.Control.Draw({
    edit: { featureGroup: drawnItems },
    draw: { polygon:true, rectangle:true, polyline:false, circle:false, marker:true, circlemarker:false }
  });
  mapInstance.addControl(drawControl);

  // Load saved zones (polygons) onto the map
  savedPuroks.forEach(p => {
    if (!p.coordinates) return;
    const type = p.type || 'zone';
    try {
      if (type === 'marker') {
        const pt = JSON.parse(p.coordinates);
        if (!pt || typeof pt.lat === 'undefined') return;
        const layer = L.circleMarker([pt.lat, pt.lng], {
          radius: 9, color: p.color, fillColor: p.color, fillOpacity: 0.9, weight: 2
        });
        layer.bindPopup(`<b>📍 ${p.name}</b><br>${p.description || ''}`);
        layer.addTo(mapInstance);
        layer._purokId = p.id;
      } else {
        const coords = JSON.parse(p.coordinates);
        if (!coords || !coords.length) return;
        const layer = L.polygon(coords, {
          color: p.color, fillColor: p.color, fillOpacity: 0.2, weight: 2
        });
        layer.bindPopup(`<b>${p.name}</b><br>${p.description || ''}`);
        layer.addTo(mapInstance);
        layer._purokId = p.id;
      }
    } catch(e) {}
  });

  // Load resident markers (from resident records, separate from admin markers above)
  fetch('api/residents_map.php')
    .then(r => r.json())
    .then(data => {
      if (!data.residents) return;
      data.residents.forEach(r => {
        if (!r.latitude || !r.longitude) return;
        L.marker([r.latitude, r.longitude])
          .bindPopup(`<b>${r.full_name}</b><br>${r.zone_purok || ''}`)
          .addTo(mapInstance);
      });
    }).catch(() => {});

  mapInstance.on('draw:created', function(e) {
    const isMarker = e.layerType === 'marker';
    // Guard against drawing the wrong tool for the selected mode
    if (currentDrawMode === 'zone' && isMarker) {
      showToast('You are in Zone/Purok mode. Switch to Marker mode to drop a pin, or use the polygon/rectangle tool.', 'error');
      return;
    }
    if (currentDrawMode === 'marker' && !isMarker) {
      showToast('You are in Marker mode. Switch to Zone/Purok mode to draw a shape, or use the marker (pin) tool.', 'error');
      return;
    }
    if (lastDrawnLayer) drawnItems.removeLayer(lastDrawnLayer);
    lastDrawnLayer = e.layer;
    drawnItems.addLayer(lastDrawnLayer);
    showToast('Drawn! Fill in the name and click Save.', 'info');
  });
});

// ── Zone vs Marker mode toggle ──────────────────────────────────
function setDrawMode(mode) {
  currentDrawMode = mode;
  document.getElementById('modeZoneBtn').classList.toggle('active', mode === 'zone');
  document.getElementById('modeMarkerBtn').classList.toggle('active', mode === 'marker');
  clearDrawing();
  showToast(mode === 'zone'
    ? 'Zone/Purok mode — use the polygon or rectangle tool.'
    : 'Marker mode — use the marker (pin) tool.', 'info');
}

async function savePurok() {
  const name  = document.getElementById('purokName').value.trim();
  const desc  = document.getElementById('purokDesc').value.trim();
  const color = document.getElementById('purokColor').value;

  if (!name) { showToast('Please enter a name.', 'error'); return; }
  if (!lastDrawnLayer) { showToast('Please draw a marker or shape on the map first.', 'error'); return; }

  let coords = '';
  let type = currentDrawMode;

  if (lastDrawnLayer instanceof L.Marker) {
    type = 'marker';
    const ll = lastDrawnLayer.getLatLng();
    coords = JSON.stringify({ lat: ll.lat, lng: ll.lng });
  } else if (lastDrawnLayer.getLatLngs) {
    type = 'zone';
    const lls = lastDrawnLayer.getLatLngs();
    const flat = Array.isArray(lls[0]) ? lls[0] : lls;
    coords = JSON.stringify(flat.map(ll => [ll.lat, ll.lng]));
  }

  if (!coords) { showToast('Could not read the drawn shape. Try drawing again.', 'error'); return; }

  const fd = new FormData();
  fd.append('action',      'save_purok');
  fd.append('name',        name);
  fd.append('description', desc);
  fd.append('color',       color);
  fd.append('coordinates', coords);
  fd.append('type',        type);

  const res  = await fetch('mapping.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message, 'success');
    setTimeout(() => location.reload(), 800);
  } else {
    showToast(data.message, 'error');
  }
}

function clearDrawing() {
  if (lastDrawnLayer) { drawnItems.removeLayer(lastDrawnLayer); lastDrawnLayer = null; }
  document.getElementById('purokName').value = '';
  document.getElementById('purokDesc').value = '';
}

async function deletePurok(id, btn) {
  if (!confirm('Delete this item?')) return;
  const fd = new FormData();
  fd.append('action','delete_purok'); fd.append('id',id);
  const res  = await fetch('mapping.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) { btn.closest('.purok-item').remove(); showToast('Deleted.','info'); }
}

function focusPurok(id) {
  const item = document.querySelector(`.purok-item[data-id="${id}"]`);
  if (!item || !mapInstance) return;
  const type = item.dataset.type;
  const raw  = item.dataset.coords;
  try {
    if (type === 'marker') {
      const pt = JSON.parse(raw);
      mapInstance.setView([pt.lat, pt.lng], 18);
    } else {
      const coords = JSON.parse(raw);
      if (coords.length) {
        const poly = L.polygon(coords);
        mapInstance.fitBounds(poly.getBounds().pad(0.2));
      }
    }
  } catch(e) {}
}

function filterByPurok(name) {
  // placeholder — full implementation would show/hide layers by purok name
  showToast(name ? `Filtering by ${name}` : 'Showing all puroks', 'info');
}
</script>
</body>
</html>