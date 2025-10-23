<?php
include_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/session.php';

// Verify admin/staff role
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
    
        if ($request_id && in_array($new_status, ['ready', 'rejected'])) {
        try {
            $handled_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $stmt = $pdo->prepare('UPDATE tbl_requests SET status = :status, reason = :reason, handled_by = :handled_by WHERE request_id = :id');
            $stmt->execute([
                'status' => $new_status,
                'reason' => $reason,
                'handled_by' => $handled_by,
                'id' => $request_id
            ]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update request']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
}