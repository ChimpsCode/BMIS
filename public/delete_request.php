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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];
    
    try {
        $stmt = $pdo->prepare('DELETE FROM tbl_requests WHERE request_id = :id');
        $stmt->execute(['id' => $request_id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete request']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}