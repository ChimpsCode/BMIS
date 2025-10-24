<?php
include_once __DIR__ . '/../includes/db.php';

try {
    // Add pending_status and release_date columns if they don't exist
    $pdo->exec('ALTER TABLE tbl_requests ADD COLUMN IF NOT EXISTS pending_status VARCHAR(50) NULL');
    $pdo->exec('ALTER TABLE tbl_requests ADD COLUMN IF NOT EXISTS release_date DATETIME NULL');
    $pdo->exec('ALTER TABLE tbl_requests ADD COLUMN IF NOT EXISTS failed VARCHAR(50) NULL');
    
    echo "Database updated successfully";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage();
}