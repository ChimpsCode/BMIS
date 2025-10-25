<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Only admin/staff can reply to inquiries
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['inquiry_id']) || !isset($input['reply'])) {
        throw new Exception('Missing required fields');
    }

    $inquiry_id = (int)$input['inquiry_id'];
    $reply = trim($input['reply']);
    $staff_id = (int)$_SESSION['user_id'];

    $pdo->beginTransaction();

    // Get the original inquiry details
    $stmt = $pdo->prepare('SELECT resident_id, subject FROM tbl_inquiries WHERE inquiry_id = ?');
    $stmt->execute([$inquiry_id]);
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inquiry) {
        throw new Exception('Inquiry not found');
    }

    // Update the inquiry with reply, staff_id, and status
    $updateStmt = $pdo->prepare('UPDATE tbl_inquiries SET reply = :reply, staff_id = :staff_id, status = :status WHERE inquiry_id = :inquiry_id');
    $updateStmt->execute([
        ':reply' => $reply,
        ':staff_id' => $staff_id,
        ':status' => 'replied',
        ':inquiry_id' => $inquiry_id
    ]);

    // Add a log entry
    $logStmt = $pdo->prepare('
        INSERT INTO tbl_logs (user_id, activity, action_type) 
        VALUES (:uid, :act, :type)
    ');
    $logStmt->execute([
        ':uid' => $staff_id,
        ':act' => 'Replied to inquiry: ' . substr($inquiry['subject'], 0, 50),
        ':type' => 'reply'
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}