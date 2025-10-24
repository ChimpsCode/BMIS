<?php
include_once __DIR__ . '/../includes/db.php';
try {
    $pdo->exec("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS soft_delete TINYINT(1) NOT NULL DEFAULT 0");
    echo "OK";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
