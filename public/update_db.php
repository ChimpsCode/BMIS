<?php
include_once __DIR__ . '/../includes/db.php';

try {
    // Add reason column if it doesn't exist
    $pdo->exec('ALTER TABLE tbl_requests ADD COLUMN IF NOT EXISTS reason TEXT NULL');
    
    echo "Database updated successfully";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage();
}