<?php
require_once __DIR__ . '/includes/db.php';
try {
    $rows = $pdo->query('DESCRIBE tbl_users')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>