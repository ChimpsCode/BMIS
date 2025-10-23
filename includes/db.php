<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection credentials
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'boss'; // <-- specify your exact database name here

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    // Show error message if connection fails
    echo '<div style="padding:12px;background:#fee;border:1px solid #faa;color:#800">
        Database connection error: ' . htmlspecialchars($e->getMessage()) . '
        <br>Please make sure the database <strong>' . htmlspecialchars($dbName) . '</strong> exists and credentials are correct.
    </div>';
    exit;
}

// $pdo is now available for use in other pages
?>
