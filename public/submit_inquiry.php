<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in as resident
if (!isset($_SESSION['resident_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login as a resident']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (empty($_POST['subject']) || empty($_POST['message'])) {
        throw new Exception('Subject and message are required');
    }

    $resident_id = (int)$_SESSION['resident_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    // Begin transaction
    $pdo->beginTransaction();

    // Debug log
    error_log("Attempting to insert inquiry for resident_id: " . $resident_id);

    // Insert into tbl_inquiries only
    $stmt = $pdo->prepare('
        INSERT INTO tbl_inquiries 
        (resident_id, subject, message, date_sent, status) 
        VALUES 
        (?, ?, ?, NOW(), ?)
    ');

    $stmt->execute([
        $resident_id,
        $subject,
        $message,
        'unread'
    ]);

    // Add to logs
    $logStmt = $pdo->prepare('
        INSERT INTO tbl_logs 
        (user_id, activity, action_type) 
        VALUES 
        (:user_id, :activity, :action_type)
    ');

    $logStmt->execute([
        ':user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0,
        ':activity' => 'New inquiry submitted: ' . substr($subject, 0, 50),
        ':action_type' => 'inquiry'
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Inquiry submitted successfully',
        'updateStats' => true
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}