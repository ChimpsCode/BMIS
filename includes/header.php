<!-
    Header / Topbar included on public pages
    This header is now session-aware and shows the logged-in role.
-->
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$currentRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
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
                <div class="small muted" style="white-space: nowrap;">Logged in as <strong class="notif-user"><?php echo htmlspecialchars(ucfirst($currentRole)); ?></strong></div>
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
