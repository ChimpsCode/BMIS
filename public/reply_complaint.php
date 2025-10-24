<?php
if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isset($_POST['complaint_id']) || !isset($_POST['message'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}
$cid = (int)$_POST['complaint_id'];
$message = trim($_POST['message']);
try {
    require_once __DIR__ . '/../includes/db.php';
    // Only admin/staff allowed to reply
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
    if ($role !== 'admin' && $role !== 'staff') {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    try {
        $stmt = $pdo->prepare('SELECT resident_id, subject, `type` FROM complaints WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $cid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // fallback if `type` column does not exist
        $stmt = $pdo->prepare('SELECT resident_id, subject FROM complaints WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $cid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $row['type'] = 'complaint';
    }
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Complaint not found']);
        exit;
    }

    $resident_id = $row['resident_id'];
    $ctype = isset($row['type']) ? $row['type'] : 'complaint';
    $subject = 'Reply (' . ucfirst($ctype) . '): ' . ($row['subject'] ?? ($ctype === 'feedback' ? 'Feedback' : 'Complaint'));

    $ins = $pdo->prepare('INSERT INTO tbl_inquiries (resident_id, subject, message, date_sent, status) VALUES (:rid, :sub, :msg, NOW(), :st)');
    $ins->execute(['rid' => $resident_id, 'sub' => $subject, 'msg' => $message, 'st' => 'unread']);

    // Optionally mark complaint as answered or append reply (not implemented here)

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
