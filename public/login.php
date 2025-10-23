<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $errors = [];

    if ($username === '' || $password === '') {
        $errors[] = 'Username and password are required.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, role, resident_id, password_hash FROM tbl_users WHERE username = :u LIMIT 1");
            $stmt->execute(['u' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

           
           
            if ($user) {
    // Check if password_hash is really hashed
    $info = password_get_info($user['password_hash']);
    if ($info['algo'] === 0) {
        // It's plain text, hash it now
        $hashed = password_hash($user['password_hash'], PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE tbl_users SET password_hash = :pass WHERE user_id = :id");
        $update->execute([
            'pass' => $hashed,
            'id' => $user['user_id']
        ]);
        // Update $user array to use new hash
        $user['password_hash'] = $hashed;
    }

    // Now verify password
    if (password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['role'] = $user['role'];
        if (!empty($user['resident_id'])) $_SESSION['resident_id'] = (int)$user['resident_id'];

        header('Location: dashboard.php');
        exit;
    } else {
        $errors[] = 'Invalid username or password.';
    }
} else {
    $errors[] = 'Invalid username or password.';
}


        } catch (Exception $e) {
            $errors[] = 'Login error: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">        
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Barangay System</title>
    <link rel="stylesheet" href="login-style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account</p>
            </div>

            <form id="loginForm" class="login-form" method="post">

                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" required autocomplete="username">
                        <label for="username">Username</label>
                        <span class="focus-border"></span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper password-wrapper">
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                        <label for="password">Password</label>
                        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Toggle password visibility">
                            <span class="eye-icon"></span>
                        </button>
                        <span class="focus-border"></span>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-wrapper">
                        <input type="checkbox" id="remember" name="remember">
                        <span class="checkbox-label">
                            <span class="checkmark"></span>
                            Remember me
                        </span>
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>

                <button type="submit" class="login-btn btn">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-loader"></span>
                </button>

                <div class="divider">
                    <span>or sign up</span>
                </div>

                <div class="signup-link">
                    <p>Don't have an account? <a href="register.php">Sign up</a></p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="card error" style="margin:12px 0">
                        <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
