<?php

session_start();
require_once __DIR__ . '/../includes/db.php'; // Ensure this path is correct

function ensure_user_columns($pdo) {
    $cols = [
        'username' => "VARCHAR(100) UNIQUE NULL",
        'password_hash' => "VARCHAR(255) NULL"
    ];
    foreach ($cols as $name => $type) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND COLUMN_NAME = :col");
        $stmt->execute(['col' => $name]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE tbl_users ADD COLUMN {$name} {$type}");
        }
    }
}

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensure_user_columns($pdo);
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
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';
    if ($fields['first_name'] === '' || $fields['last_name'] === '' || $fields['birthdate'] === '' || $username === '' || $password === '') {
        $errors[] = 'Please fill required fields.';
    }
    if ($password !== $password2) $errors[] = 'Passwords do not match.';
    // Check for existing resident by full name
    $checkStmt = $pdo->prepare('SELECT resident_id, contact_no FROM tbl_residents WHERE first_name = ? AND middle_name = ? AND last_name = ?');
    $checkStmt->execute([$fields['first_name'], $fields['middle_name'], $fields['last_name']]);
    $existingResident = $checkStmt->fetch();
    $showVerifyForm = false;
    if ($existingResident && empty($_POST['verify_contact'])) {
        $showVerifyForm = true;
    }
    if (!$showVerifyForm) {
        if ($is_head) {
            $head_contact = trim($_POST['head_contact'] ?? '');
            $house_no = trim($_POST['house_no'] ?? '');
            $purok = trim($_POST['purok'] ?? '');
            if ($house_no === '') $errors[] = 'House number is required.';
            if ($purok === '') $errors[] = 'Purok is required.';
        } else {
        // household_id is set by dropdown and logic above; no need to reassign or error here
        }
    }
    if (!$showVerifyForm && empty($errors)) {
        try {
            // Insert new resident into tbl_residents
            $insertResident = $pdo->prepare('INSERT INTO tbl_residents (first_name, middle_name, last_name, suffix, birthdate, gender, civil_status, occupation, citizenship, address, voter_status, relation_to_head, household_id, created_at, updated_at) VALUES (:fn,:mn,:ln,:sx,:bd,:gd,:cs,:oc,:ct,:ad,:vs,:rh,:hid,NOW(),NOW())');
            $insertResident->execute([
                ':fn' => $fields['first_name'],
                ':mn' => $fields['middle_name'],
                ':ln' => $fields['last_name'],
                ':sx' => $fields['suffix'],
                ':bd' => $fields['birthdate'],
                ':gd' => $fields['gender'],
                ':cs' => $fields['civil_status'],
                ':oc' => $fields['occupation'],
                ':ct' => $fields['citizenship'],
                ':ad' => $fields['address'],
                ':vs' => $fields['voter_status'],
                ':rh' => $fields['relation_to_head'],
                ':hid' => $fields['household_id']
            ]);

            $new_resident_id = (int)$pdo->lastInsertId();

            // Create user account
            $pw_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO tbl_users (username, password_hash, role, resident_id, account_status) VALUES (:u, :p, :role, :rid, :status)');
            $stmt->execute([
                ':u' => $username,
                ':p' => $pw_hash,
                ':role' => 'resident',
                ':rid' => $new_resident_id,
                ':status' => 'Active'
            ]);

            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['role'] = 'resident';
            $_SESSION['resident_id'] = $new_resident_id;
            $success = 'Account created. Redirecting...';
            header('Refresh:1; url=dashboard.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Registration error: ' . $e->getMessage();
        }
    }
    // Handle verification form submission
    if ($existingResident && isset($_POST['verify_contact'])) {
        $verify_contact = trim($_POST['verify_contact']);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password_confirm'] ?? '';
        if ($verify_contact === '') $errors[] = 'Please enter your contact number.';
        if ($password !== $password2) $errors[] = 'Passwords do not match.';
        // Check username uniqueness
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE username = :u");
        $stmt->execute(['u' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Username already taken.';
        }
        if ($verify_contact !== ($existingResident['contact_no'] ?? '')) {
            $errors[] = 'Contact number does not match our records.';
        }
        if (empty($errors)) {
            // Create user account for existing resident
            $pw_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO tbl_users (role, resident_id, username, password_hash) VALUES (:role,:rid,:u,:p)');
            $stmt->execute([
                'role' => 'resident', 'rid' => $existingResident['resident_id'], 'u' => $username, 'p' => $pw_hash
            ]);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['role'] = 'resident';
            $_SESSION['resident_id'] = $existingResident['resident_id'];
            $success = 'Account verified and created. Redirecting...';
            header('Refresh:1; url=dashboard.php');
            exit;
        }
        $showVerifyForm = true;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Register - Barangay System</title>
        <link rel="stylesheet" href="login-style.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>Sign Up</h2>
                <p>Create your account</p>
            </div>

            <?php if ($success): ?>
                <div class="card success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="card error" style="margin:12px 0">
                    <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($showVerifyForm)) { ?>
            <form class="login-form" method="post" id="verifyForm" novalidate>
                <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                <input type="hidden" name="middle_name" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                <div class="card error">Your information is already registered. Please input the number you've used in your registration to verify if it's you, then set your username and password.</div>
                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="text" name="verify_contact" required>
                        <label>Contact Number</label>
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="text" name="username" required>
                        <label>Username</label>
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-wrapper password-wrapper">
                        <input type="password" name="password" required>
                        <label>Password</label>
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-wrapper password-wrapper">
                        <input type="password" name="password_confirm" required>
                        <label>Confirm Password</label>
                        <span class="focus-border"></span>
                    </div>
                </div>
                <button class="login-btn btn" type="submit">
                    <span class="btn-text">Verify & Create Account</span>
                    <span class="btn-loader"></span>
                </button>
                <div class="signup-link">
                    <a class="btn ghost" href="login.php">Back to login</a>
                </div>
            </form>
            <?php } else { ?>
            <form class="login-form" method="post" id="residentSignUpForm" novalidate>
                <div class="form-group">
                    <label for="is_head_select" style="color: #fff;">Are you the head of the family?</label>
                    <div class="input-wrapper">
                        <select name="is_head" id="is_head_select" required>
                            <option value="head">Head of Family</option>
                            <option value="member">Family Member</option>
                        </select>
                        
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div id="headFields">
                    <div class="form-group always-top-label">
                        <div class="input-wrapper always-top-label">
                            <label for="head_contact_input" class="always-top">Contact Number</label>
                            <input type="text" name="head_contact" id="head_contact_input">
                            <span class="focus-border"></span>
                        </div>
                    </div>
                    <div class="form-group always-top-label">
                        <div class="input-wrapper always-top-label">
                            <label for="house_no_input" class="always-top">House No.</label>
                            <input type="text" name="house_no" id="house_no_input">
                            <span class="focus-border"></span>
                        </div>
                    </div>
                    <div class="form-group always-top-label">
                        <div class="input-wrapper always-top-label">
                            <label for="purok_input" class="always-top">Purok</label>
                            <input type="text" name="purok" id="purok_input">
                            <span class="focus-border"></span>
                        </div>
                    </div>
                </div>
                <div id="memberFields" style="display:none;">
                    <div class="form-group">
                        <label for="household_id_select" style="color: #fff;">Select Household</label>
                        <div class="input-wrapper">
                            <select name="household_id" id="household_id_select">
                                <option value="">-- Select Household --</option>
                                <?php
                                $households = $pdo->query('SELECT household_id, house_no, purok FROM tbl_households')->fetchAll();
                                foreach ($households as $hh) {
                                    echo '<option value="' . (int)$hh['household_id'] . '">Household #' . htmlspecialchars($hh['house_no']) . ' - Purok ' . htmlspecialchars($hh['purok']) . '</option>';
                                }
                                ?>
                            </select>
                            
                            <span class="focus-border"></span>
                        </div>
                    </div>
                </div>
                <div class="form-group always-top-label">
                    <div class="input-wrapper always-top-label">
                        <label class="always-top">First name</label>
                        <input type="text" class="highlight-box" name="first_name">
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group always-top-label">
                    <div class="input-wrapper always-top-label">
                        <label class="always-top">Middle name</label>
                        <input type="text" name="middle_name">
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group always-top-label">
                    <div class="input-wrapper always-top-label">
                        <label class="always-top">Last name</label>
                        <input type="text" class="highlight-box" name="last_name">
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group always-top-label">
                    <div class="input-wrapper always-top-label">
                        <label class="always-top">Suffix</label>
                        <input type="text" name="suffix">
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group always-top-label">
                    <div class="input-wrapper always-top-label">
                        <label class="always-top">Birthdate (MM/DD/YYYY)</label>
                        <input type="date" name="birthdate" required placeholder="MM/DD/YYYY">
                        <span class="focus-border"></span>
                    </div>
                </div>
                <label style="color: #fff;">Gender</label>
                <div class="form-group">
                    <div class="input-wrapper">
                        <select name="gender">
                            <option>Male</option>
                            <option>Female</option>
                            <option>Other</option>
                        </select>
                        
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label style="color: #fff;">Civil Status</label>
                    <div class="input-wrapper">
                        
                        <select name="civil_status">
                            <option>Single</option>
                            <option>Married</option>
                            <option>Widowed</option>
                            <option>Separated</option>
                        </select>
                        
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group always-top-label">
                    <div class="input-wrapper always-top-label">
                        <label class="always-top">Occupation</label>
                        <input type="text" name="occupation">
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group always-top-label">
                    <div class="input-wrapper always-top-label">
                        <label class="always-top">Citizenship</label>
                        <input type="text" name="citizenship">
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label style="color: #fff;">Voter Status</label>
                    <div class="input-wrapper">
                        <select name="voter_status">
                            <option>Registered</option>
                            <option selected>Unregistered</option>
                        </select>
                        
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group always-top-label">
                    <div class="input-wrapper always-top-label">
                        <label class="always-top">Relation to Head</label>
                        <input type="text" name="relation_to_head">
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group always-top-label">
                    <div class="input-wrapper always-top-label">
                        <label class="always-top">Address</label>
                        <input type="text" name="address">
                        <span class="focus-border"></span>
                    </div>
                </div>
                
                
                <hr>
                <br>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="text" name="username" required>
                        <label>Username</label>
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-wrapper password-wrapper">
                        <input type="password" name="password" required>
                        <label>Password</label>
                        <span class="focus-border"></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-wrapper password-wrapper">
                        <input type="password" name="password_confirm" required>
                        <label>Confirm Password</label>
                        <span class="focus-border"></span>
                    </div>
                </div>
                <button class="login-btn btn" type="submit">
                    <span class="btn-text">Create account</span>
                    <span class="btn-loader"></span>
                </button>
                <div class="signup-link">
                    <a class="btn-ghost" href="login.php">Back to login</a>
                </div>
            </form>
            <?php } ?>
        </div>
    </div>
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
</body>
</html>