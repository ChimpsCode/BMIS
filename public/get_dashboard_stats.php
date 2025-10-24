<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

// Only admin/staff can access stats
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // total residents
    $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM tbl_residents');
    $totalResidents = (int)$stmt->fetchColumn();

    // pending requests
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tbl_requests 
         WHERE (pending_status IS NULL OR TRIM(pending_status) = '' OR LOWER(pending_status) = 'pending') 
         OR LOWER(status) IN ('pending','processing')"
    );
    $stmt->execute();
    $pendingRequests = (int)$stmt->fetchColumn();

    // complaints (not soft deleted)
    $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM complaints WHERE soft_delete = 0 OR soft_delete IS NULL');
    $complaints = (int)$stmt->fetchColumn();

    // inquiries
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_messages WHERE LOWER(subject) LIKE :q OR LOWER(content) LIKE :q");
    $q = '%inquiry%';
    $stmt->execute(['q' => $q]);
    $inquiries = (int)$stmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => [
            'totalResidents' => $totalResidents,
            'pendingRequests' => $pendingRequests,
            'complaints' => $complaints,
            'inquiries' => $inquiries
        ]
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}