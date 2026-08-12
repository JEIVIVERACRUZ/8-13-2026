<?php
// ============================================================
// households.php  — Admin: Household management
// ============================================================
session_start();
require_once 'includes/db.php';
requireAdminLogin();

// ── AJAX handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Create household
    if ($action === 'create_household') {
        $headId     = (int)($_POST['head_id']      ?? 0);
        $address    = trim($_POST['address']       ?? '');
        $zone       = trim($_POST['zone']          ?? '');
        $houseNo    = trim($_POST['house_number']  ?? '');

        if (!$headId) jsonError('Please select a household head.');

        // Check if this resident is already a head
        $existing = dbFetchOne("SELECT id FROM households WHERE household_head=?", [$headId]);
        if ($existing) jsonError('This resident is already a household head.');

        $hhId = dbInsert(
            "INSERT INTO households (household_head, address, zone_purok, house_number, member_count)
             VALUES (?,?,?,?,1)",
            [$headId, $address, $zone, $houseNo]
        );

        // Link resident to household and mark as head
        dbExecute(
            "UPDATE residents SET household_id=?, is_household_head=1 WHERE id=?",
            [$hhId, $headId]
        );

        // Auto-add head as first member
        $head = dbFetchOne("SELECT CONCAT(first_name,' ',last_name) AS full_name FROM residents WHERE id=?", [$headId]);
        dbInsert(
            "INSERT INTO household_members (household_id, resident_id, full_name, relation, status)
             VALUES (?,?,?,'Head','Verified')",
            [$hhId, $headId, $head['full_name']]
        );

        logActivity("Created household #$hhId", 'Households');
        jsonOk(['id' => $hhId], 'Household created successfully.');
    }

    // Add member
    if ($action === 'add_member') {
        $hhId      = (int)($_POST['household_id'] ?? 0);
        $fullName  = trim($_POST['full_name']     ?? '');
        $relation  = trim($_POST['relation']      ?? '');
        $age       = (int)($_POST['age']          ?? 0);
        $resId     = (int)($_POST['resident_id']  ?? 0) ?: null;

        if (!$hhId || !$fullName || !$relation) jsonError('Household, name, and relation are required.');

        dbInsert(
            "INSERT INTO household_members (household_id, resident_id, full_name, relation, age, status)
             VALUES (?,?,?,?,?,'Pending')",
            [$hhId, $resId, $fullName, $relation, $age ?: null]
        );

        // Update member count
        dbExecute(
            "UPDATE households SET member_count=(SELECT COUNT(*) FROM household_members WHERE household_id=?) WHERE id=?",
            [$hhId, $hhId]
        );

        logActivity("Added member to household #$hhId", 'Households');
        jsonOk([], 'Member added.');
    }

    // Remove member
    if ($action === 'remove_member') {
        $memberId = (int)($_POST['member_id']    ?? 0);
        $hhId     = (int)($_POST['household_id'] ?? 0);

        // Don't allow removing the head
        $member = dbFetchOne("SELECT relation FROM household_members WHERE id=?", [$memberId]);
        if ($member && $member['relation'] === 'Head') jsonError('Cannot remove the household head. Delete the household instead.');

        dbExecute("DELETE FROM household_members WHERE id=?", [$memberId]);
        dbExecute(
            "UPDATE households SET member_count=(SELECT COUNT(*) FROM household_members WHERE household_id=?) WHERE id=?",
            [$hhId, $hhId]
        );

        logActivity("Removed member #$memberId from household #$hhId", 'Households');
        jsonOk([], 'Member removed.');
    }

    // Delete household
    if ($action === 'delete_household') {
        $hhId = (int)($_POST['id'] ?? 0);

        // Get the current head before unlinking, so we can clear their old approved request
        $headResident = dbFetchOne("SELECT id FROM residents WHERE household_id=? AND is_household_head=1", [$hhId]);

        // Unlink all residents
        dbExecute("UPDATE residents SET household_id=NULL, is_household_head=0 WHERE household_id=?", [$hhId]);
        dbExecute("DELETE FROM households WHERE id=?", [$hhId]);

        // Clear out the head's old 'Approved' head_request so they can request again.
        // Without this, $isApproved stays true on resident_myprofile.php and the resident
        // gets stuck seeing "Your request was approved!" with no way to re-apply.
        if ($headResident) {
            dbExecute(
                "DELETE FROM head_requests WHERE resident_id=? AND status='Approved'",
                [$headResident['id']]
            );
        }

        logActivity("Deleted household #$hhId", 'Households');
        jsonOk([], 'Household deleted.');
    }

    // Review head request (approve / reject)
    if ($action === 'review_head_request') {
        $reqId   = (int)($_POST['request_id'] ?? 0);
        $verdict = trim($_POST['verdict']     ?? ''); // 'Approved' or 'Rejected'
        $note    = trim($_POST['admin_note']  ?? '');

        if (!in_array($verdict, ['Approved', 'Rejected'])) jsonError('Invalid verdict.');

        $req = dbFetchOne(
            "SELECT hr.*, CONCAT(r.first_name,' ',r.last_name) AS full_name,
                    r.house_number, r.zone_purok, r.street_address, r.is_household_head
             FROM head_requests hr
             JOIN residents r ON hr.resident_id = r.id
             WHERE hr.id=? AND hr.status='Pending'",
            [$reqId]
        );
        if (!$req) jsonError('Request not found or already processed.');

        if ($verdict === 'Approved') {
            $id = (int)$req['resident_id'];

            if ($req['is_household_head']) {
                dbExecute("UPDATE head_requests SET status='Rejected', admin_note='Already a household head.' WHERE id=?", [$reqId]);
                jsonError('This resident is already a household head.');
            }

            // Duplicate address guard
            $dup = dbFetchOne(
                "SELECT id FROM households
                 WHERE LOWER(TRIM(house_number)) = LOWER(TRIM(?))
                   AND LOWER(TRIM(zone_purok))   = LOWER(TRIM(?))
                 LIMIT 1",
                [$req['house_number'], $req['zone_purok']]
            );
            if ($dup) {
                dbExecute("UPDATE head_requests SET status='Rejected', admin_note='A household already exists at this address.' WHERE id=?", [$reqId]);
                jsonError('A household already exists at that address. Request auto-rejected.');
            }

            // Create household
            $hhId = dbInsert(
                "INSERT INTO households (household_head, address, zone_purok, house_number, member_count)
                 VALUES (?,?,?,?,1)",
                [$id, $req['street_address'], $req['zone_purok'], $req['house_number']]
            );

            // Promote resident
            dbExecute(
                "UPDATE residents SET household_id=?, is_household_head=1 WHERE id=?",
                [$hhId, $id]
            );

            // Add as Head member
            dbInsert(
                "INSERT INTO household_members (household_id, resident_id, full_name, relation, status)
                 VALUES (?,?,?,'Head','Verified')",
                [$hhId, $id, $req['full_name']]
            );

            // Auto-suggest same-address residents
            $sameAddress = dbFetchAll(
                "SELECT id, CONCAT(first_name,' ',last_name) AS full_name
                 FROM residents
                 WHERE id != ?
                   AND LOWER(TRIM(house_number)) = LOWER(TRIM(?))
                   AND LOWER(TRIM(zone_purok))   = LOWER(TRIM(?))
                   AND status != 'Inactive'",
                [$id, $req['house_number'], $req['zone_purok']]
            );
            foreach ($sameAddress as $nb) {
                dbInsert(
                    "INSERT INTO household_members (household_id, resident_id, full_name, relation, status)
                     VALUES (?,?,?,'Relative','Pending')",
                    [$hhId, $nb['id'], $nb['full_name']]
                );
                dbExecute("UPDATE residents SET household_id=? WHERE id=?", [$hhId, $nb['id']]);
            }
            dbExecute(
                "UPDATE households SET member_count=(SELECT COUNT(*) FROM household_members WHERE household_id=?) WHERE id=?",
                [$hhId, $hhId]
            );

            dbExecute(
                "UPDATE head_requests SET status='Approved', admin_note=? WHERE id=?",
                [$note, $reqId]
            );
            logActivity("Approved head request #$reqId — promoted resident {$req['resident_id']} (HH #$hhId)", 'Households');
            jsonOk(['household_id' => $hhId], 'Request approved. Household #' . $hhId . ' created.');
        } else {
            dbExecute(
                "UPDATE head_requests SET status='Rejected', admin_note=? WHERE id=?",
                [$note ?: 'Request was not approved by the admin.', $reqId]
            );
            logActivity("Rejected head request #$reqId", 'Households');
            jsonOk([], 'Request rejected.');
        }
    }

    // Update member
    if ($action === 'update_member') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $fullName = trim($_POST['full_name']  ?? '');
        $relation = trim($_POST['relation']   ?? '');
        $age      = (int)($_POST['age']       ?? 0);
        $status   = trim($_POST['status']     ?? 'Pending');

        if (!$fullName || !$relation) jsonError('Name and relation are required.');

        dbExecute(
            "UPDATE household_members SET full_name=?, relation=?, age=?, status=? WHERE id=?",
            [$fullName, $relation, $age ?: null, $status, $memberId]
        );
        logActivity("Updated household member #$memberId", 'Households');
        jsonOk([], 'Member updated.');
    }

    exit;
}

// ── Data ──────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$where  = $search ? "WHERE CONCAT(r.first_name,' ',r.last_name) LIKE ? OR h.house_number LIKE ? OR h.zone_purok LIKE ?" : '';
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$households = dbFetchAll("
    SELECT h.*,
           CONCAT(r.first_name,' ',r.last_name) AS head_name,
           r.barangay_id AS head_bid
    FROM households h
    LEFT JOIN residents r ON h.household_head = r.id
    $where
    ORDER BY h.created_at DESC
", $params);

// All residents for head selector (not yet a household head)
$availableResidents = dbFetchAll("
    SELECT id, CONCAT(first_name,' ',last_name) AS full_name, barangay_id, house_number, zone_purok
    FROM residents
    WHERE is_household_head = 0 AND status != 'Inactive'
    ORDER BY first_name
");

$puroks = dbFetchAll("SELECT name FROM puroks ORDER BY name");
$zones  = $puroks ?: [['name'=>'Zone 1'],['name'=>'Zone 2'],['name'=>'Zone 3'],['name'=>'Zone 4']];

$totalHouseholds = count($households);
$totalMembers    = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM household_members")['c'] ?? 0);

// Pending household head requests from residents (paginated, 5 at a time)
$hreqPerPage = 5;
$hreqPage    = max(1, (int)($_GET['hreq_page'] ?? 1));

$totalHeadRequests = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM head_requests WHERE status='Pending'"
)['c'] ?? 0);
$totalHeadReqPages = max(1, (int)ceil($totalHeadRequests / $hreqPerPage));
if ($hreqPage > $totalHeadReqPages) $hreqPage = $totalHeadReqPages;
$hreqOffset = ($hreqPage - 1) * $hreqPerPage;

$pendingHeadRequests = dbFetchAll("
    SELECT hr.*,
           CONCAT(r.first_name,' ',r.last_name) AS full_name,
           r.barangay_id, r.email, r.phone,
           r.house_number, r.zone_purok, r.street_address,
           r.status AS resident_status
    FROM head_requests hr
    JOIN residents r ON hr.resident_id = r.id
    WHERE hr.status = 'Pending'
    ORDER BY hr.created_at ASC
    LIMIT $hreqPerPage OFFSET $hreqOffset
");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Households — Barangay San Isidro</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php renderThemeStyle(); ?>
</head>
<body>
<div class="app">
  <?php include 'includes/sidebar_admin.php'; ?>
  <main class="main">
    <div class="topbar">
      <div>
        <h3>Household Management</h3>
        <div class="text-muted">Manage household records and family members by house number.</div>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('createModal').style.display='flex'">+ Create Household</button>
      </div>
    </div>

    <div class="stats">
      <div class="stat">
        <h4>Total Households</h4>
        <p data-target="<?= $totalHouseholds ?>">0</p>
        <div class="stat-icon">🏠</div>
        <div class="stat-change positive">Registered</div>
      </div>
      <div class="stat">
        <h4>Total Members</h4>
        <p data-target="<?= $totalMembers ?>">0</p>
        <div class="stat-icon">👥</div>
        <div class="stat-change positive">Across all households</div>
      </div>
      <div class="stat">
        <h4>Available Residents</h4>
        <p data-target="<?= count($availableResidents) ?>">0</p>
        <div class="stat-icon">👤</div>
        <div class="stat-change positive">Not yet assigned</div>
      </div>
    </div>

    <!-- Pending Household Head Requests -->
    <?php if ($pendingHeadRequests || $hreqPage > 1): ?>
    <div class="card" id="pendingHeadRequests" style="border-left:4px solid #f59e0b">
      <h3 style="margin-bottom:4px">📨 Pending Household Head Requests <span style="font-size:14px;font-weight:400;color:#92400e;background:#fef3c7;border:1px solid #fcd34d;border-radius:12px;padding:2px 10px;margin-left:8px"><?= $totalHeadRequests ?></span></h3>
      <p class="text-muted" style="font-size:13px;margin-bottom:16px">Residents requesting to be recognized as household heads. Review and approve or reject each request.</p>
      <div style="display:grid;gap:12px">
        <?php foreach ($pendingHeadRequests as $req): ?>
        <div id="hreq-<?= $req['id'] ?>" style="border:1px solid #e5e7eb;border-radius:10px;padding:16px;background:#fafafa">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
            <div>
              <div style="font-weight:600;font-size:15px;color:#111827"><?= htmlspecialchars($req['full_name']) ?> <span style="font-size:12px;color:#6b7280;font-weight:400"><?= htmlspecialchars($req['barangay_id']) ?></span></div>
              <div style="font-size:13px;color:#374151;margin-top:4px">
                📍 House <strong><?= htmlspecialchars($req['house_number']) ?></strong>, <?= htmlspecialchars($req['zone_purok']) ?>
                <?php if ($req['street_address']): ?> — <?= htmlspecialchars($req['street_address']) ?><?php endif; ?>
              </div>
              <?php if ($req['reason']): ?>
              <div style="font-size:13px;color:#6b7280;margin-top:6px;font-style:italic">"<?= htmlspecialchars($req['reason']) ?>"</div>
              <?php endif; ?>
              <div style="font-size:11px;color:#9ca3af;margin-top:4px">Submitted <?= date('M j, Y g:i A', strtotime($req['created_at'])) ?></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;min-width:200px">
              <input type="text" id="note-<?= $req['id'] ?>" class="form-control" style="font-size:12px;padding:6px 10px" placeholder="Admin note (optional)">
              <div style="display:flex;gap:8px">
                <button class="btn btn-sm" style="background:#dcfce7;color:#166534;border:1px solid #86efac;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;flex:1"
                  onclick="reviewHeadRequest(<?= $req['id'] ?>, 'Approved')">✓ Approve</button>
                <button class="btn btn-sm" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;flex:1"
                  onclick="reviewHeadRequest(<?= $req['id'] ?>, 'Rejected')">✕ Reject</button>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$pendingHeadRequests): ?>
        <p class="text-muted" style="text-align:center;padding:12px">No requests on this page.</p>
        <?php endif; ?>
      </div>

      <!-- Pagination: 5 requests at a time -->
      <?php if ($totalHeadReqPages > 1): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:14px;flex-wrap:wrap;gap:8px">
        <span class="text-muted" style="font-size:13px">
          Showing <?= $totalHeadRequests ? ($hreqOffset + 1) : 0 ?>–<?= min($hreqOffset + $hreqPerPage, $totalHeadRequests) ?> of <?= $totalHeadRequests ?> requests
        </span>
        <div style="display:flex;gap:8px">
          <?php if ($hreqPage > 1): ?>
            <a href="?hreq_page=<?= $hreqPage - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>#pendingHeadRequests" class="btn btn-secondary btn-sm">← Previous</a>
          <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled>← Previous</button>
          <?php endif; ?>
          <?php if ($hreqPage < $totalHeadReqPages): ?>
            <a href="?hreq_page=<?= $hreqPage + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>#pendingHeadRequests" class="btn btn-secondary btn-sm">Next →</a>
          <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled>Next →</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="card">
      <form method="GET" style="display:flex;gap:12px;align-items:flex-end">
        <div class="form-group" style="flex:1;margin:0">
          <label>Search by Head Name, House Number, or Zone</label>
          <input type="text" name="search" placeholder="Type to search..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button class="btn btn-primary" type="submit">Search</button>
        <a href="households.php" class="btn">Clear</a>
      </form>
    </div>

    <!-- Households list -->
    <div class="card">
      <h3>All Households</h3>
      <?php if ($households): ?>
      <div style="display:grid;gap:16px;margin-top:16px">
        <?php foreach ($households as $hh):
          $members = dbFetchAll(
              "SELECT * FROM household_members WHERE household_id=? ORDER BY relation='Head' DESC, id ASC",
              [$hh['id']]
          );
        ?>
        <div class="card" style="border:1px solid var(--border);margin:0" id="hh-<?= $hh['id'] ?>">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
            <div>
              <h4 style="margin:0 0 4px">
                🏠 House <?= htmlspecialchars($hh['house_number'] ?: '—') ?>
                <span style="font-size:13px;font-weight:400;color:var(--text-light);margin-left:8px"><?= htmlspecialchars($hh['zone_purok'] ?: '') ?></span>
              </h4>
              <div style="font-size:13px;color:var(--text-light)">
                Head: <strong><?= htmlspecialchars($hh['head_name'] ?? '—') ?></strong>
                <?= $hh['head_bid'] ? '(' . htmlspecialchars($hh['head_bid']) . ')' : '' ?>
                &bull; <?= (int)$hh['member_count'] ?> member<?= $hh['member_count'] != 1 ? 's' : '' ?>
              </div>
              <?php if ($hh['address']): ?>
              <div style="font-size:12px;color:var(--text-light);margin-top:2px">📍 <?= htmlspecialchars($hh['address']) ?></div>
              <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <button class="btn btn-sm" style="background:#3b82f6;color:white;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:12px"
                onclick="openAddMember(<?= $hh['id'] ?>)">+ Add Member</button>
              <button class="btn btn-sm" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:12px"
                onclick="deleteHousehold(<?= $hh['id'] ?>, this)">Delete</button>
            </div>
          </div>

          <!-- Members table -->
          <div class="table-container" style="margin-top:14px">
            <table>
              <thead>
                <tr><th>Name</th><th>Relation</th><th>Age</th><th>Status</th><th>Action</th></tr>
              </thead>
              <tbody id="members-<?= $hh['id'] ?>">
                <?php foreach ($members as $m):
                  $badge = $m['status']==='Verified'?'badge-success':($m['status']==='Inactive'?'badge-danger':'badge-warning');
                ?>
                <tr id="mrow-<?= $m['id'] ?>">
                  <td><?= htmlspecialchars($m['full_name']) ?></td>
                  <td><?= htmlspecialchars($m['relation']) ?></td>
                  <td><?= $m['age'] ?: '—' ?></td>
                  <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($m['status']) ?></span></td>
                  <td style="display:flex;gap:6px">
                    <button class="btn btn-sm" style="background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;padding:4px 8px;border-radius:6px;cursor:pointer;font-size:12px"
                      onclick="openEditMember(<?= htmlspecialchars(json_encode($m)) ?>)">Edit</button>
                    <?php if ($m['relation'] !== 'Head'): ?>
                    <button class="btn btn-sm" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:4px 8px;border-radius:6px;cursor:pointer;font-size:12px"
                      onclick="removeMember(<?= $m['id'] ?>, <?= $hh['id'] ?>, this)">Remove</button>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$members): ?>
                <tr><td colspan="5" style="text-align:center;color:#aaa;padding:12px">No members yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="text-muted" style="padding:20px 0">No households found. Click "+ Create Household" to get started.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Create Household Modal -->
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;padding:28px;width:90%;max-width:500px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="margin:0">Create Household</h3>
      <button onclick="document.getElementById('createModal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af">✕</button>
    </div>
    <div style="display:grid;gap:14px">
      <div class="form-group">
        <label>Household Head *</label>
        <select id="chHead" class="form-control">
          <option value="">Select resident...</option>
          <?php foreach ($availableResidents as $r): ?>
          <option value="<?= $r['id'] ?>"
            data-house="<?= htmlspecialchars($r['house_number'] ?? '') ?>"
            data-zone="<?= htmlspecialchars($r['zone_purok'] ?? '') ?>">
            <?= htmlspecialchars($r['full_name']) ?> (<?= htmlspecialchars($r['barangay_id']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>House Number</label>
        <input type="text" id="chHouseNo" class="form-control" placeholder="e.g., Lot 5, Blk 2">
      </div>
      <div class="form-group">
        <label>Zone / Purok</label>
        <select id="chZone" class="form-control">
          <option value="">Select...</option>
          <?php foreach ($zones as $z): ?>
          <option value="<?= htmlspecialchars($z['name']) ?>"><?= htmlspecialchars($z['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Full Address</label>
        <input type="text" id="chAddress" class="form-control" placeholder="e.g., 123 Sampaguita St.">
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button class="btn btn-primary" style="flex:1" onclick="createHousehold()">Create</button>
        <button class="btn btn-secondary" style="flex:1" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Member Modal -->
<div id="addMemberModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;padding:28px;width:90%;max-width:460px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="margin:0">Add Member</h3>
      <button onclick="document.getElementById('addMemberModal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af">✕</button>
    </div>
    <input type="hidden" id="amHhId">
    <div style="display:grid;gap:14px">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" id="amName" class="form-control" placeholder="Member's full name">
      </div>
      <div class="form-group">
        <label>Relation to Head *</label>
        <select id="amRelation" class="form-control">
          <option value="">Select...</option>
          <option>Spouse</option><option>Son</option><option>Daughter</option>
          <option>Father</option><option>Mother</option><option>Sibling</option>
          <option>Grandchild</option><option>Grandparent</option><option>Relative</option><option>Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Age</label>
        <input type="number" id="amAge" class="form-control" placeholder="Age" min="0" max="120">
      </div>
      <div class="form-group">
        <label>Link to Registered Resident (optional)</label>
        <select id="amResidentId" class="form-control">
          <option value="">Not a registered resident</option>
          <?php foreach ($availableResidents as $r): ?>
          <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['full_name']) ?> (<?= htmlspecialchars($r['barangay_id']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button class="btn btn-primary" style="flex:1" onclick="addMember()">Add Member</button>
        <button class="btn btn-secondary" style="flex:1" onclick="document.getElementById('addMemberModal').style.display='none'">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Member Modal -->
<div id="editMemberModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:12px;padding:28px;width:90%;max-width:420px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="margin:0">Edit Member</h3>
      <button onclick="document.getElementById('editMemberModal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af">✕</button>
    </div>
    <input type="hidden" id="emId">
    <div style="display:grid;gap:14px">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" id="emName" class="form-control">
      </div>
      <div class="form-group">
        <label>Relation *</label>
        <select id="emRelation" class="form-control">
          <option>Head</option><option>Spouse</option><option>Son</option><option>Daughter</option>
          <option>Father</option><option>Mother</option><option>Sibling</option>
          <option>Grandchild</option><option>Grandparent</option><option>Relative</option><option>Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Age</label>
        <input type="number" id="emAge" class="form-control" min="0" max="120">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select id="emStatus" class="form-control">
          <option value="Verified">Verified</option>
          <option value="Pending">Pending</option>
          <option value="Inactive">Inactive</option>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button class="btn btn-primary" style="flex:1" onclick="saveMember()">Save</button>
        <button class="btn btn-secondary" style="flex:1" onclick="document.getElementById('editMemberModal').style.display='none'">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
// Auto-fill house/zone when head is selected
document.getElementById('chHead')?.addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  if (opt.dataset.house) document.getElementById('chHouseNo').value = opt.dataset.house;
  if (opt.dataset.zone)  document.getElementById('chZone').value    = opt.dataset.zone;
});

async function createHousehold() {
  const fd = new FormData();
  fd.append('action',       'create_household');
  fd.append('head_id',      document.getElementById('chHead').value);
  fd.append('house_number', document.getElementById('chHouseNo').value);
  fd.append('zone',         document.getElementById('chZone').value);
  fd.append('address',      document.getElementById('chAddress').value);
  const res = await fetch('households.php', {method:'POST', body:fd});
  const d   = await res.json();
  if (d.success) { showToast(d.message, 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(d.message, 'error');
}

// ── Head Request Review ───────────────────────────────────────
async function reviewHeadRequest(reqId, verdict) {
  const confirmMsg = verdict === 'Approved'
    ? 'Approve this request? A new household will be created and the resident will be promoted to head.'
    : 'Reject this request?';
  if (!confirm(confirmMsg)) return;

  const note = document.getElementById('note-' + reqId)?.value.trim() || '';

  const fd = new FormData();
  fd.append('action',      'review_head_request');
  fd.append('request_id',  reqId);
  fd.append('verdict',     verdict);
  fd.append('admin_note',  note);

  const res  = await fetch('households.php', { method:'POST', body: fd });
  const data = await res.json();

  if (data.success) {
    showToast(data.message, 'success');
    document.getElementById('hreq-' + reqId)?.remove();
    setTimeout(() => location.reload(), 800);
  } else {
    showToast(data.message || 'Error', 'error');
  }
}

function openAddMember(hhId) {
  document.getElementById('amHhId').value = hhId;
  document.getElementById('amName').value = '';
  document.getElementById('amRelation').value = '';
  document.getElementById('amAge').value = '';
  document.getElementById('amResidentId').value = '';
  document.getElementById('addMemberModal').style.display = 'flex';
}

async function addMember() {
  const fd = new FormData();
  fd.append('action',       'add_member');
  fd.append('household_id', document.getElementById('amHhId').value);
  fd.append('full_name',    document.getElementById('amName').value);
  fd.append('relation',     document.getElementById('amRelation').value);
  fd.append('age',          document.getElementById('amAge').value);
  fd.append('resident_id',  document.getElementById('amResidentId').value);
  const res = await fetch('households.php', {method:'POST', body:fd});
  const d   = await res.json();
  if (d.success) { showToast(d.message, 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(d.message, 'error');
}

function openEditMember(m) {
  document.getElementById('emId').value       = m.id;
  document.getElementById('emName').value     = m.full_name;
  document.getElementById('emRelation').value = m.relation;
  document.getElementById('emAge').value      = m.age || '';
  document.getElementById('emStatus').value   = m.status;
  document.getElementById('editMemberModal').style.display = 'flex';
}

async function saveMember() {
  const fd = new FormData();
  fd.append('action',    'update_member');
  fd.append('member_id', document.getElementById('emId').value);
  fd.append('full_name', document.getElementById('emName').value);
  fd.append('relation',  document.getElementById('emRelation').value);
  fd.append('age',       document.getElementById('emAge').value);
  fd.append('status',    document.getElementById('emStatus').value);
  const res = await fetch('households.php', {method:'POST', body:fd});
  const d   = await res.json();
  if (d.success) { showToast(d.message, 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(d.message, 'error');
}

async function removeMember(memberId, hhId, btn) {
  if (!confirm('Remove this member from the household?')) return;
  const fd = new FormData();
  fd.append('action',       'remove_member');
  fd.append('member_id',    memberId);
  fd.append('household_id', hhId);
  const res = await fetch('households.php', {method:'POST', body:fd});
  const d   = await res.json();
  if (d.success) {
    document.getElementById('mrow-' + memberId)?.remove();
    showToast('Member removed.', 'info');
  } else showToast(d.message, 'error');
}

async function deleteHousehold(id, btn) {
  if (!confirm('Delete this entire household? This cannot be undone.')) return;
  const fd = new FormData();
  fd.append('action', 'delete_household');
  fd.append('id', id);
  const res = await fetch('households.php', {method:'POST', body:fd});
  const d   = await res.json();
  if (d.success) {
    document.getElementById('hh-' + id)?.remove();
    showToast('Household deleted.', 'info');
  } else showToast(d.message, 'error');
}
</script>
</body>
</html>