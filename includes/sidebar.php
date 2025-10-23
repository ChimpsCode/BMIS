<?php
// Usage: include 'includes/sidebar.php';
// Prefer session role if available, otherwise use $role variable (admin, staff, resident)
$menus = [
    'admin' => [
        'Dashboard' => 'dashboard.php',
        'User Management' => 'user_management.php',
        'Resident Record' => 'resident_record.php',
        'Messages' => 'messages.php',
        'Document Requests' => 'document_requests.php',
        'Complaints & Feedback' => 'complaints_feedback.php',
    ],
    'staff' => [
        'Dashboard' => 'dashboard.php',
        'Messages' => 'messages.php',
        'Resident Record' => 'resident_record.php',
        'Document Requests' => 'document_requests.php',
        'Complaints & Feedback' => 'complaints_feedback.php',
    ],
    'resident' => [
        'Dashboard' => 'dashboard.php',
        'Document Requests' => 'document_requests.php',
        'Messages' => 'messages.php',
        'Complaints & Feedback' => 'complaints_feedback.php',
    ]
];
?>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// prefer session role when set
$menuRole = isset($_SESSION['role']) ? $_SESSION['role'] : (isset($role) ? $role : 'resident');
?>
<div class="sidebar">
    <div class="brand">
        <div class="logo">BR</div>
        <div>
            <h2>Barangay</h2>
            <div class="small muted">Resident System</div>
        </div>
    </div>
    <ul>
        <?php foreach ($menus[$menuRole] as $label => $link): ?>
            <li><a href="<?php echo $link; ?>" class="<?php echo (basename($_SERVER['PHP_SELF']) == $link) ? 'active' : ''; ?>"><?php echo $label; ?></a></li>
        <?php endforeach; ?>
    </ul>
    <div class="sidebar-footer">
        <?php if (isset($_SESSION['role'])): ?>
            <a href="../public/logout.php" class="btn" style="width:100%;justify-content:center">Logout</a>
        <?php else: ?>
            <a href="../public/login.php" class="btn" style="width:100%;justify-content:center">Logout</a>
        <?php endif; ?>
    </div>
</div>
