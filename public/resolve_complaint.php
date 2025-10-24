<?php
if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isset($_POST['complaint_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing complaint_id']);
    exit;
}
$cid = (int)$_POST['complaint_id'];
try {
    require_once __DIR__ . '/../includes/db.php';
    // Only admin/staff allowed to mark resolved
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
    if ($role !== 'admin' && $role !== 'staff') {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    // Start transaction to ensure both update and log are saved
    $pdo->beginTransaction();

    // Get complaint details first for logging
    $getDetails = $pdo->prepare('SELECT subject, type FROM complaints WHERE id = :id');
    $getDetails->execute(['id' => $cid]);
    $complaint = $getDetails->fetch(PDO::FETCH_ASSOC);
    
    // Update complaint status
    $upd = $pdo->prepare('UPDATE complaints SET status = :st, resolved_at = NOW() WHERE id = :id');
    $upd->execute(['st' => 'resolved', 'id' => $cid]);
    
    // Add log entry for admin/staff action
    $logStmt = $pdo->prepare('INSERT INTO tbl_logs (user_id, activity, action_type) VALUES (:uid, :act, :type)');
    $logStmt->execute([
        ':uid' => $_SESSION['user_id'],
        ':act' => 'Resolved ' . ($complaint['type'] ?? 'complaint') . ': ' . ($complaint['subject'] ?? 'ID #' . $cid),
        ':type' => 'resolve'
    ]);

    // Commit transaction
    $pdo->commit();
    
    // notify resident via tbl_inquiries if possible
    try {
        try {
            $notif = $pdo->prepare('SELECT resident_id, subject, `type` FROM complaints WHERE id = :id LIMIT 1');
            $notif->execute(['id' => $cid]);
            $r = $notif->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $notif = $pdo->prepare('SELECT resident_id, subject FROM complaints WHERE id = :id LIMIT 1');
            $notif->execute(['id' => $cid]);
            $r = $notif->fetch(PDO::FETCH_ASSOC);
            if ($r) $r['type'] = 'complaint';
        }
        if ($r && !empty($r['resident_id'])) {
            $ctype = isset($r['type']) ? $r['type'] : 'complaint';
            $replySub = 'Notification (' . ucfirst($ctype) . '): ' . ($r['subject'] ?? 'Update');
            $ins = $pdo->prepare('INSERT INTO tbl_inquiries (resident_id, subject, message, date_sent, status) VALUES (:rid, :sub, :msg, NOW(), :st)');
            $ins->execute(['rid' => $r['resident_id'], 'sub' => $replySub, 'msg' => 'Your ' . $ctype . ' has been marked as resolved.', 'st' => 'unread']);
        }
    } catch (Exception $e) { /* ignore notify errors */ }

    echo json_encode([
        'success' => true,
        'message' => 'Complaint resolved successfully',
        'updateStats' => true // Signal frontend to update stats
    ]);
} catch (Exception $e) {
    // Rollback transaction if there was an error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
exit;
