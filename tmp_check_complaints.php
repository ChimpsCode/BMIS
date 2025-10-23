<?php
require_once __DIR__ . '/includes/db.php';
try {
    $stmt = $pdo->query('SELECT id AS complaint_id, resident_id, subject, details, status, created_at FROM complaints ORDER BY id DESC LIMIT 10');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>