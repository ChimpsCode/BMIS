<?php
include_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json; charset=utf-8');
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}
$cid = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
if (!$cid) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing complaint id']);
    exit;
}
try {
    $stmt = $pdo->prepare('UPDATE complaints SET soft_delete = 1 WHERE id = :id');
    $stmt->execute(['id' => $cid]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
}
