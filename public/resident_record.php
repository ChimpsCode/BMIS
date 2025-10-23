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

                /* ------------------------
                 * DELETE RESIDENT
                 * ------------------------
                 */
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
                    $delId = (int)$_POST['delete_id'];

                    try {
                        $stmt = $pdo->prepare('DELETE FROM tbl_residents WHERE resident_id = :id');
                        $stmt->execute(['id' => $delId]);

                        echo '<div class="card success">Resident deleted successfully.</div>';
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

                    $stmt = $pdo->query('SELECT COUNT(*) FROM tbl_residents');
                    $total = (int)$stmt->fetchColumn();

                    $totalPages = max(1, ceil($total / $perPage));
                    $page = min($page, $totalPages);
                    $start = ($page - 1) * $perPage;

                    $stmt = $pdo->prepare('
                        SELECT resident_id, first_name, last_name, address 
                        FROM tbl_residents 
                        ORDER BY last_name, first_name 
                        LIMIT :start, :per
                    ');
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
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                    <td class="small muted">-</td>
                                    <td class="small muted">-</td>
                                    <td class="small muted">-</td>
                                    <td class="small muted">-</td>
                                    <td>
                                        <form method="post" style="display:inline;"
                                              onsubmit="return confirm('Delete this resident?')">
                                            <input type="hidden" name="delete_id"
                                                   value="<?= (int)$r['resident_id']; ?>">
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
                </script>
            </div>
        </div>
    </main>
</body>
</html>
