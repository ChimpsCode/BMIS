<!-
    Header / Topbar included on public pages
    This header is now session-aware and shows the logged-in role.
-->
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$currentRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
// Attempt to determine a friendly display name for the logged in user
$displayName = null;
// Prefer an explicit username stored in session
if (!empty($_SESSION['username'])) {
    $displayName = $_SESSION['username'];
} else {
    // If user_id is available, try to fetch username from DB
    if (!empty($_SESSION['user_id'])) {
        // include DB connection if available
        $dbPath = __DIR__ . '/db.php';
        if (file_exists($dbPath)) {
            try {
                require_once $dbPath;
                $uStmt = $pdo->prepare('SELECT username, resident_id FROM tbl_users WHERE user_id = :uid LIMIT 1');
                $uStmt->execute([':uid' => (int)$_SESSION['user_id']]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                if ($uRow && !empty($uRow['username'])) {
                    $displayName = $uRow['username'];
                } else if ($uRow && !empty($uRow['resident_id'])) {
                    // fallback to resident full name
                    $rStmt = $pdo->prepare('SELECT CONCAT_WS(" ", first_name, middle_name, last_name, suffix) AS full_name FROM tbl_residents WHERE resident_id = :rid LIMIT 1');
                    $rStmt->execute([':rid' => (int)$uRow['resident_id']]);
                    $rRow = $rStmt->fetch(PDO::FETCH_ASSOC);
                    if ($rRow && !empty($rRow['full_name'])) $displayName = $rRow['full_name'];
                }
            } catch (Exception $e) {
                // ignore DB errors here, fallback to role-only display
                error_log('Header display name lookup failed: ' . $e->getMessage());
            }
        }
    }
    // If still empty and resident session exists, try resident name
    if (empty($displayName) && !empty($_SESSION['resident_id'])) {
        $dbPath = __DIR__ . '/db.php';
        if (file_exists($dbPath)) {
            try {
                require_once $dbPath;
                $rStmt = $pdo->prepare('SELECT CONCAT_WS(" ", first_name, middle_name, last_name, suffix) AS full_name FROM tbl_residents WHERE resident_id = :rid LIMIT 1');
                $rStmt->execute([':rid' => (int)$_SESSION['resident_id']]);
                $rRow = $rStmt->fetch(PDO::FETCH_ASSOC);
                if ($rRow && !empty($rRow['full_name'])) $displayName = $rRow['full_name'];
            } catch (Exception $e) {
                error_log('Header resident lookup failed: ' . $e->getMessage());
            }
        }
    }
    // Final fallback: role name
    if (empty($displayName) && $currentRole) {
        $displayName = ucfirst($currentRole);
    }
}
?>
<link rel="stylesheet" href="../assets/css/style.css">
<div class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button id="sidebarToggle" class="btn ghost" onclick="toggleSidebar()">☰</button>
        <div class="title">Barangay Resident Information and Document Request System</div>
    </div>
    <div class="actions">
        <?php if ($currentRole): ?>
            <div class="notif" style="margin-left: auto; display: flex; align-items: center; gap: 8px;">
                <button class="notif-btn" title="Notifications" style="margin-right: 8px;">
                    🔔
                    <span class="badge">3</span>
                </button>
                <div class="small muted" style="white-space: nowrap;">Logged in as <strong class="notif-user"><?php echo htmlspecialchars($displayName ?? ucfirst($currentRole)); ?></strong> (<?php echo htmlspecialchars(ucfirst($currentRole)); ?>)</div>
            </div>
        <?php else: ?>
            <a href="../public/login.php" class="btn">Login</a>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSidebar(){
    var sb = document.querySelector('.sidebar');
    if(!sb) return;
    sb.classList.toggle('open');
}
</script>
