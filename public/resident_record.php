<?php
// Get the role from URL (default to 'resident')
$role = isset($_GET['role']) ? $_GET['role'] : 'resident';
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Resident Record - Barangay System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <main class="main-content container">
        <div class="card">
            <!-- Header Section -->
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                <div>
                    <h2 style="margin: 0;">Resident Record</h2>
                    <p class="muted" style="margin: 4px 0 0 0;">
                        List of residents with contact, status, last login, and creation date.
                    </p>
                </div>
                <div>
                    <a href="resident_record.php?action=add" class="btn">+ Add Resident</a>
                </div>
            </div>

            <div style="margin-top: 12px; overflow: auto;">
                <?php
                // Include database connection
                include_once __DIR__ . '/../includes/db.php';

                // Detect schema features (optional columns)
                $hasResidentContact = false;
                $hasCreatedAt = false;
                $hasSoftDelete = false;
                try {
                    $c = $pdo->query("SHOW COLUMNS FROM tbl_residents LIKE 'contact_no'")->fetch();
                    if ($c) $hasResidentContact = true;
                } catch (Exception $e) { /* ignore */ }
                try {
                    $c2 = $pdo->query("SHOW COLUMNS FROM tbl_residents LIKE 'created_at'")->fetch();
                    if ($c2) $hasCreatedAt = true;
                } catch (Exception $e) { /* ignore */ }
                try {
                    $c3 = $pdo->query("SHOW COLUMNS FROM tbl_residents LIKE 'soft_delete'")->fetch();
                    if ($c3) $hasSoftDelete = true;
                } catch (Exception $e) { /* ignore */ }

                // Handle inline updates from admin/staff (update contact/status)
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
                    $updId = (int)$_POST['update_id'];
                    $newContact = isset($_POST['contact']) ? trim($_POST['contact']) : null;
                    $newStatus = isset($_POST['account_status']) ? trim($_POST['account_status']) : null;
                    try {
                        if ($newContact !== null) {
                            if ($hasResidentContact) {
                                $u = $pdo->prepare('UPDATE tbl_residents SET contact_no = :c WHERE resident_id = :id');
                                $u->execute(['c' => $newContact, 'id' => $updId]);
                            } else {
                                // fallback: update household contact if resident belongs to one
                                $hstmt = $pdo->prepare('SELECT household_id FROM tbl_residents WHERE resident_id = :id LIMIT 1');
                                $hstmt->execute(['id' => $updId]);
                                $hh = $hstmt->fetch();
                                if ($hh && !empty($hh['household_id'])) {
                                    $pdo->prepare('UPDATE tbl_households SET contact_no = :c WHERE household_id = :hid')->execute(['c' => $newContact, 'hid' => $hh['household_id']]);
                                }
                            }
                        }
                        if ($newStatus !== null) {
                            // update tbl_users account_status when a user exists for this resident
                            $ust = $pdo->prepare('UPDATE tbl_users SET account_status = :s WHERE resident_id = :rid');
                            $ust->execute(['s' => $newStatus, 'rid' => $updId]);
                        }
                        echo '<div class="card success">Resident updated.</div>';
                    } catch (Exception $e) {
                        echo '<div class="card error">Unable to update resident: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }

                /* ------------------------
                 * DELETE RESIDENT
                 * ------------------------
                 */
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
                    $delId = (int)$_POST['delete_id'];

                    try {
                        // Prefer soft-delete to avoid FK constraint errors and preserve audit/history.
                        if ($hasSoftDelete) {
                            $stmt = $pdo->prepare('UPDATE tbl_residents SET soft_delete = 1 WHERE resident_id = :id');
                            $stmt->execute(['id' => $delId]);
                        } else {
                            // Try to add the soft_delete column (non-destructive). If that fails, fall back to attempting a hard delete.
                            try {
                                $pdo->exec("ALTER TABLE tbl_residents ADD COLUMN soft_delete TINYINT(1) NOT NULL DEFAULT 0");
                                $hasSoftDelete = true;
                                $stmt = $pdo->prepare('UPDATE tbl_residents SET soft_delete = 1 WHERE resident_id = :id');
                                $stmt->execute(['id' => $delId]);
                            } catch (Exception $inner) {
                                // Could not add column (permissions?), fallback to application-level check: prevent deletion if dependent rows exist
                                $chk = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE resident_id = :id');
                                $chk->execute(['id' => $delId]);
                                $cnt = (int)$chk->fetchColumn();
                                if ($cnt > 0) {
                                    throw new Exception("There are {$cnt} complaint(s) referencing this resident. Delete or reassign them first.");
                                }
                                // No dependent complaints found, attempt hard delete
                                $stmt = $pdo->prepare('DELETE FROM tbl_residents WHERE resident_id = :id');
                                $stmt->execute(['id' => $delId]);
                            }
                        }

                        echo '<div class="card success">Resident deleted (soft-deleted if supported) successfully.</div>';
                    } catch (Exception $e) {
                        echo '<div class="card error">Unable to delete resident: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }

                /* ------------------------
                 * ADD RESIDENT (MODAL FORM)
                 * ------------------------
                 */
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_modal') {
                    $is_head = ($_POST['is_head'] ?? '') === 'head';
                    $fields = [
                        'household_id' => null,
                        'first_name' => trim($_POST['first_name'] ?? ''),
                        'last_name' => trim($_POST['last_name'] ?? ''),
                        'middle_name' => trim($_POST['middle_name'] ?? ''),
                        'suffix' => trim($_POST['suffix'] ?? ''),
                        'birthdate' => trim($_POST['birthdate'] ?? ''),
                        'gender' => $_POST['gender'] ?? 'Other',
                        'civil_status' => $_POST['civil_status'] ?? 'Single',
                        'occupation' => trim($_POST['occupation'] ?? ''),
                        'citizenship' => trim($_POST['citizenship'] ?? ''),
                        'address' => trim($_POST['address'] ?? ''),
                        'voter_status' => $_POST['voter_status'] ?? 'Unregistered',
                        'relation_to_head' => trim($_POST['relation_to_head'] ?? ''),
                    ];
                    $errors = [];
                    if ($fields['first_name'] === '') $errors[] = 'First name is required.';
                    if ($fields['last_name'] === '') $errors[] = 'Last name is required.';
                    if ($fields['birthdate'] === '') $errors[] = 'Birthdate is required.';
                    if ($is_head) {
                        $head_contact = trim($_POST['head_contact'] ?? '');
                        $house_no = trim($_POST['house_no'] ?? '');
                        $purok = trim($_POST['purok'] ?? '');
                        if ($house_no === '') $errors[] = 'House number is required.';
                        if ($purok === '') $errors[] = 'Purok is required.';
                    } else {
                        $fields['household_id'] = $_POST['household_id'] !== '' ? (int)$_POST['household_id'] : null;
                        if (!$fields['household_id']) $errors[] = 'Please select a household.';
                    }
                    if (empty($errors)) {
                        try {
                            if ($is_head) {
                                // Use resident's name as head name
                                $head_name = trim(($fields['first_name'] ?? '') . ' ' . ($fields['middle_name'] ?? '') . ' ' . ($fields['last_name'] ?? ''));
                                $stmt = $pdo->prepare('INSERT INTO tbl_households (house_no, purok, head_resident_id, contact_no) VALUES (?, ?, NULL, ?)');
                                $stmt->execute([$house_no, $purok, $head_contact]);
                                $household_id = $pdo->lastInsertId();
                                $fields['household_id'] = $household_id;
                            }
                            $stmt = $pdo->prepare('
                                INSERT INTO tbl_residents 
                                (household_id, first_name, last_name, middle_name, suffix, birthdate, gender, civil_status, occupation, citizenship, address, voter_status, relation_to_head)
                                VALUES (:household_id, :first_name, :last_name, :middle_name, :suffix, :birthdate, :gender, :civil_status, :occupation, :citizenship, :address, :voter_status, :relation_to_head)
                            ');
                            $stmt->execute($fields);
                            if ($is_head) {
                                // Update household with new head_resident_id
                                $new_resident_id = $pdo->lastInsertId();
                                $pdo->prepare('UPDATE tbl_households SET head_resident_id = ? WHERE household_id = ?')->execute([$new_resident_id, $fields['household_id']]);
                            }
                            echo '<div class="card success">Resident added successfully.</div>';
                        } catch (Exception $e) {
                            echo '<div class="card error">Unable to add resident: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    } else {
                        echo '<div class="card error">' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
                    }
                }

                /* ------------------------
                 * FETCH RESIDENTS
                 * ------------------------
                 */
                try {
                    $perPage = 5;
                    $page = max(1, (int)($_GET['page'] ?? 1));

                    // Exclude soft-deleted rows from listing when supported
                    $whereClause = '';
                    if ($hasSoftDelete) {
                        $whereClause = 'WHERE r.soft_delete = 0';
                    }

                    $countSql = 'SELECT COUNT(*) FROM tbl_residents r ' . $whereClause;
                    $stmt = $pdo->query($countSql);
                    $total = (int)$stmt->fetchColumn();

                    $totalPages = max(1, ceil($total / $perPage));
                    $page = min($page, $totalPages);
                    $start = ($page - 1) * $perPage;

                    // Build select list dynamically depending on available columns
                    $selectCols = 'r.resident_id, r.first_name, r.last_name, r.address, r.household_id';
                    if ($hasResidentContact) $selectCols .= ', r.contact_no';
                    $selectCols .= ', u.account_status, u.last_login';
                    if ($hasCreatedAt) $selectCols .= ', r.created_at';

                    $stmt = $pdo->prepare(
                        'SELECT ' . $selectCols . ' , h.contact_no AS household_contact FROM tbl_residents r LEFT JOIN tbl_households h ON r.household_id = h.household_id LEFT JOIN tbl_users u ON u.resident_id = r.resident_id ' . $whereClause . ' ORDER BY r.last_name, r.first_name LIMIT :start, :per'
                    );
                    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
                    $stmt->bindValue(':per', $perPage, PDO::PARAM_INT);
                    $stmt->execute();

                    $rows = $stmt->fetchAll();
                } catch (Exception $e) {
                    echo '<div class="muted">Unable to load residents. Check database connection.</div>';
                    $rows = [];
                    $total = 0;
                    $totalPages = 1;
                    $page = 1;
                }
                ?>

                <!-- Modal Form -->
                <div id="modalOverlay"
                     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);
                            align-items:center;justify-content:center;z-index:9999;">
                    <div style="background:var(--card);padding:18px;border-radius:10px;
                                min-width:320px;max-width:600px;box-shadow:0 12px 40px rgba(2,6,23,0.4);">
                        <h3 style="margin:0 0 8px 0;">Add Resident</h3>

                        <form method="post" id="residentAddForm">
                            <input type="hidden" name="action" value="add_modal">
                            <div style="margin-bottom:8px;">
                                <label>Is this person the head of the family?</label>
                                <select name="is_head" id="is_head_select" required>
                                    <option value="head">Head of Family</option>
                                    <option value="member">Family Member</option>
                                </select>
                            </div>
                            <div id="headFields" style="display:block;">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                    <div>
                                        <label>Contact Number</label>
                                        <input type="text" name="head_contact" id="head_contact_input">
                                    </div>
                                    <div>
                                        <label>House No.</label>
                                        <input type="text" name="house_no" id="house_no_input">
                                    </div>
                                    <div>
                                        <label>Purok</label>
                                        <input type="text" name="purok" id="purok_input">
                                    </div>
                                </div>
                            </div>
                            <div id="memberFields" style="display:none;">
                                <div style="margin-top:8px;">
                                    <label>Select Household</label>
                                    <select name="household_id" id="household_id_select">
                                        <option value="">-- Select Household --</option>
                                        <?php
                                        $households = $pdo->query('SELECT household_id, house_no, purok FROM tbl_households')->fetchAll();
                                        foreach ($households as $hh) {
                                            echo '<option value="' . (int)$hh['household_id'] . '">Household #' . htmlspecialchars($hh['house_no']) . ' - Purok ' . htmlspecialchars($hh['purok']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
                                <div>
                                    <label>First name</label>
                                    <input type="text" name="first_name" required>
                                </div>
                                <div>
                                    <label>Last name</label>
                                    <input type="text" name="last_name" required>
                                </div>
                                <div>
                                    <label>Middle name</label>
                                    <input type="text" name="middle_name">
                                </div>
                                <div>
                                    <label>Suffix</label>
                                    <input type="text" name="suffix">
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
                                <div>
                                    <label>Birthdate</label>
                                    <input type="date" name="birthdate" required>
                                </div>
                                <div>
                                    <label>Gender</label>
                                    <select name="gender">
                                        <option>Male</option>
                                        <option>Female</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label>Civil Status</label>
                                    <select name="civil_status">
                                        <option>Single</option>
                                        <option>Married</option>
                                        <option>Widowed</option>
                                        <option>Separated</option>
                                    </select>
                                </div>
                                <div>
                                    <label>Occupation</label>
                                    <input type="text" name="occupation">
                                </div>
                                <div>
                                    <label>Citizenship</label>
                                    <input type="text" name="citizenship">
                                </div>
                                <div>
                                    <label>Voter Status</label>
                                    <select name="voter_status">
                                        <option>Registered</option>
                                        <option selected>Unregistered</option>
                                    </select>
                                </div>
                                <div>
                                    <label>Relation to Head</label>
                                    <input type="text" name="relation_to_head">
                                </div>
                            </div>
                            <div style="margin-top:8px;">
                                <label>Address</label>
                                <input type="text" name="address">
                            </div>
                            <div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end;">
                                <button class="btn" type="submit">Save</button>
                                <button type="button" id="closeModal" class="btn ghost">Cancel</button>
                            </div>
                        </form>
                        <script>
                        // Toggle head/member fields
                        document.addEventListener('DOMContentLoaded', function() {
                            var isHeadSelect = document.getElementById('is_head_select');
                            var headFields = document.getElementById('headFields');
                            var memberFields = document.getElementById('memberFields');
                            function toggleFields() {
                                if (isHeadSelect.value === 'head') {
                                    headFields.style.display = 'block';
                                    memberFields.style.display = 'none';
                                } else {
                                    headFields.style.display = 'none';
                                    memberFields.style.display = 'block';
                                }
                            }
                            isHeadSelect.addEventListener('change', toggleFields);
                            toggleFields();
                        });
                        </script>
                    </div>
                </div>

                <!-- Residents Table -->
                <div>
                    <table>
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): 
                                $contact = '-';
                                if (!empty($r['contact_no'])) $contact = htmlspecialchars($r['contact_no']);
                                elseif (!empty($r['household_contact'])) $contact = htmlspecialchars($r['household_contact']);
                                $status = !empty($r['account_status']) ? htmlspecialchars($r['account_status']) : '-';
                                $lastLogin = !empty($r['last_login']) ? htmlspecialchars($r['last_login']) : '-';
                                $created = !empty($r['created_at']) ? htmlspecialchars($r['created_at']) : '-';
                            ?>
                                <tr id="resident-row-<?= (int)$r['resident_id'] ?>">
                                    <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                    <td class="small muted contact-cell"><?= $contact ?></td>
                                    <td class="small muted status-cell"><?= $status ?></td>
                                    <td class="small muted"><?= $lastLogin ?></td>
                                    <td class="small muted"><?= $created ?></td>
                                    <td>
                                        <button class="btn" type="button" onclick="editResident(<?= (int)$r['resident_id'] ?>, <?= json_encode($contact) ?>, <?= json_encode($status) ?>)">Edit</button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this resident?')">
                                            <input type="hidden" name="delete_id" value="<?= (int)$r['resident_id']; ?>">
                                            <button class="btn ghost" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total > 0 && $totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a class="btn pager" href="resident_record.php?page=<?= $page - 1; ?>">&larr; Previous</a>
                        <?php else: ?>
                            <span class="btn pager disabled">&larr; Previous</span>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="btn pager" href="resident_record.php?page=<?= $page + 1; ?>">Next &rarr;</a>
                        <?php else: ?>
                            <span class="btn pager disabled">Next &rarr;</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Modal Script -->
                <script>
                    (function() {
                        const modal = document.getElementById('modalOverlay');
                        const openBtn = document.querySelector('a.btn[href="resident_record.php?action=add"]');
                        const closeBtn = document.getElementById('closeModal');

                        if (openBtn && modal) {
                            openBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                modal.style.display = 'flex';
                            });
                        }

                        if (closeBtn && modal) {
                            closeBtn.addEventListener('click', function() {
                                modal.style.display = 'none';
                            });
                        }

                        // Close modal when clicking outside
                        if (modal) {
                            modal.addEventListener('click', function(e) {
                                if (e.target === modal) modal.style.display = 'none';
                            });
                        }
                    })();
                    // Edit resident helper
                    function editResident(id, currentContact, currentStatus) {
                        // prompt for contact and status (simple flow)
                        var contact = prompt('Contact number:', currentContact && currentContact !== '-' ? currentContact : '');
                        if (contact === null) return; // cancelled
                        var status = prompt('Account status (Active/Inactive):', currentStatus && currentStatus !== '-' ? currentStatus : 'Active');
                        if (status === null) return;

                        var fd = new FormData();
                        fd.append('update_id', id);
                        fd.append('contact', contact);
                        fd.append('account_status', status);

                        fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd })
                            .then(r => r.text())
                            .then(html => {
                                // simple approach: reload the page to reflect changes
                                window.location.reload();
                            }).catch(err => alert('Error updating resident: ' + err.message));
                    }
                </script>
            </div>
        </div>
    </main>
</body>
</html>
