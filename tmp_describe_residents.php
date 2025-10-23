<?php
require_once __DIR__ . '/includes/db.php';
try {
    $stmt = $pdo->query("DESCRIBE tbl_residents");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>