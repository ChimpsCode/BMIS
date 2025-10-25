<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Only admin/staff can update inquiry status
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['inquiry_id']) || !isset($input['status'])) {
        throw new Exception('Missing required fields');
    }

    $inquiry_id = (int)$input['inquiry_id'];
    $status = trim($input['status']);
    $staff_id = (int)$_SESSION['user_id'];

    // Update inquiry status
    $stmt = $pdo->prepare('
        UPDATE tbl_inquiries 
        SET status = ?, 
            staff_id = ?,
            updated_at = NOW()
        WHERE inquiry_id = ?
    ');

    $stmt->execute([$status, $staff_id, $inquiry_id]);

    // Add log entry
    $logStmt = $pdo->prepare('
        INSERT INTO tbl_logs 
        (user_id, activity, action_type) 
        VALUES 
        (?, ?, ?)
    ');

    $logStmt->execute([
        $staff_id,
        "Marked inquiry #$inquiry_id as $status",
        'inquiry_update'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}