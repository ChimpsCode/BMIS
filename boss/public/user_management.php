<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    $status = $_POST['status'] ?? 'Active';
    if ($username && $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO tbl_users (username, password_hash, role, account_status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hashed, $role, $status]);
    }
    header('Location: user_management.php');
    exit;
}

// Handle delete user
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    // Prevent admin from deleting themselves
    if ($deleteId !== (int)($_SESSION['user_id'] ?? 0)) {
        $pdo->prepare("DELETE FROM tbl_users WHERE user_id = ?")->execute([$deleteId]);
    }
    header('Location: user_management.php');
    exit;
}

// Fetch users from database
$users = $pdo->query("SELECT user_id, username, role, account_status, last_login FROM tbl_users ORDER BY user_id ASC")->fetchAll();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Management - Barangay System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/header.php'; ?>

<main class="main-content container">
    <div class="card" >
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
            <div>
                <h2 style="margin:0">User Management</h2>
                <p class="muted" style="margin:4px 0 0 0">Manage system users: roles, status, last login and creation date.</p>
            </div>
            <div>
                <a href="user_management.php?action=add" class="btn">+ Add User</a>
            </div>
        </div>

        <div style="margin-top:12px;overflow:auto">

            <?php
            ?>

            <form method="post" style="margin-bottom:16px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                <div>
                    <label>Username<br><input type="text" name="username" required></label>
                </div>
                <div>
                    <label>Password<br><input type="password" name="password" required></label>
                </div>
                <div>
                    <label>Role<br>
                        <select name="role">
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                            <option value="resident">Resident</option>
                        </select>
                    </label>
                </div>
                <div>
                    <label>Status<br>
                        <select name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </label>
                </div>
                <button type="submit" name="add_user" class="btn">Add User</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <!-- <th>Created</th> -->
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($u['role'])); ?></td>
                            <td><?php echo htmlspecialchars($u['account_status']); ?></td>
                            <td><?php echo htmlspecialchars($u['last_login'] ?? '-'); ?></td>
                            <!-- <td><?php echo htmlspecialchars($u['created_at'] ?? '-'); ?></td> -->
                            <td>
                                <?php if ($u['role'] !== 'admin'): ?>
                                    <a href="user_management.php?delete=<?php echo $u['user_id']; ?>" class="btn ghost" onclick="return confirm('Delete this user?')">Delete</a>
                                <?php else: ?>
                                    <span class="btn ghost disabled">Delete</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>



        </div>
    </div>
</main>

</body>
</html>
