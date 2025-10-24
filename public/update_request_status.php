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
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
    $handled_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if (!$request_id || !$action) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }

    try {
        switch ($action) {
            case 'pickup':
                // mark payment as paid and pending_status as Ready for Pick Up
                $stmt = $pdo->prepare('UPDATE tbl_requests SET payment_status = :pstatus, pending_status = :pstat, handled_by = :hb WHERE request_id = :id');
                $stmt->execute([
                    'pstatus' => 'Paid',
                    'pstat' => 'Ready for Pick Up',
                    'hb' => $handled_by,
                    'id' => $request_id
                ]);
                break;

            case 'release':
                // mark released and set release_date
                $stmt = $pdo->prepare('UPDATE tbl_requests SET pending_status = :pstat, release_date = NOW(), handled_by = :hb WHERE request_id = :id');
                $stmt->execute([
                    'pstat' => 'Released',
                    'hb' => $handled_by,
                    'id' => $request_id
                ]);
                break;

            case 'failed':
                // mark failed in status and pending_status; save reason if provided
                $stmt = $pdo->prepare('UPDATE tbl_requests SET status = :status, pending_status = :pstat, reason = :reason, handled_by = :hb WHERE request_id = :id');
                $stmt->execute([
                    'status' => 'failed',
                    'pstat' => 'Failed',
                    'reason' => $reason,
                    'hb' => $handled_by,
                    'id' => $request_id
                ]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                exit;
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
}