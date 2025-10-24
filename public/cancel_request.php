<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

if (session_status() == PHP_SESSION_NONE) session_start();

// Only allow residents to cancel their own pending requests
if (!isset($_SESSION['resident_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['request_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$request_id = (int)$_POST['request_id'];
$resident_id = (int)$_SESSION['resident_id'];

try {
    // Verify ownership and current status
    $s = $pdo->prepare('SELECT request_id, status, pending_status FROM tbl_requests WHERE request_id = :id AND resident_id = :rid LIMIT 1');
    $s->execute(['id' => $request_id, 'rid' => $resident_id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
        exit;
    }

    $status = strtolower(trim($row['pending_status'] ? $row['pending_status'] : $row['status']));

    // Residents are allowed to REMOVE (delete) their own requests only after the request is released/completed.
    $allowedToDelete = in_array($status, ['released', 'completed', 'ready', 'ready for pickup', 'released']);
    if (!$allowedToDelete) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete request at this stage. You may delete requests only after they are released/completed.']);
        exit;
    }

    // Perform deletion of the request (resident-initiated removal)
    try {
        $del = $pdo->prepare('DELETE FROM tbl_requests WHERE request_id = :id AND resident_id = :rid');
        $del->execute(['id' => $request_id, 'rid' => $resident_id]);

        // log deletion
        try {
            $logStmt = $pdo->prepare('INSERT INTO tbl_logs (user_id, activity) VALUES (:uid, :act)');
            $logStmt->execute(['uid' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, 'act' => 'Resident deleted request_id ' . $request_id]);
        } catch (Exception $e) { /* ignore logging errors */ }

        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete request']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
