<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Only admin/staff can delete logs
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['delete_all']) && $_POST['delete_all'] === 'true') {
            // Delete all logs for admin/staff
            $stmt = $pdo->prepare('DELETE l FROM tbl_logs l 
                                 INNER JOIN tbl_users u ON l.user_id = u.user_id 
                                 WHERE u.role IN ("admin", "staff")');
            $stmt->execute();
            $count = $stmt->rowCount();
            
            // Log the bulk deletion
            $logStmt = $pdo->prepare('INSERT INTO tbl_logs (user_id, activity, action_type) VALUES (:uid, :act, :atype)');
            $logStmt->execute([
                ':uid' => $_SESSION['user_id'],
                ':act' => "Deleted all admin/staff logs ({$count} entries)",
                ':atype' => 'delete'
            ]);
        } else if (isset($_POST['log_ids'])) {
            $ids = json_decode($_POST['log_ids'], true);
            if (!is_array($ids) || empty($ids)) {
                throw new Exception('No valid log IDs provided');
            }
            $ids = array_map('intval', $ids);
            
            // Delete selected logs (only if they belong to admin/staff)
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $pdo->prepare("DELETE l FROM tbl_logs l 
                                 INNER JOIN tbl_users u ON l.user_id = u.user_id 
                                 WHERE l.log_id IN ({$placeholders}) 
                                 AND u.role IN ('admin', 'staff')");
            $stmt->execute($ids);
            $count = $stmt->rowCount();
            
            // Log the selective deletion
            $logStmt = $pdo->prepare('INSERT INTO tbl_logs (user_id, activity, action_type) VALUES (:uid, :act, :atype)');
            $logStmt->execute([
                ':uid' => $_SESSION['user_id'],
                ':act' => "Deleted {$count} selected log entries",
                ':atype' => 'delete'
            ]);
        } else {
            throw new Exception('Invalid request');
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Logs deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}