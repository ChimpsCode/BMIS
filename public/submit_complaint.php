<?php
// Prevent any HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// JSON response
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/db.php';

// Simple JSON response helper
function respond($ok, $data = []) {
    echo json_encode(array_merge(['ok' => $ok], $data));
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, ['error' => 'Invalid request method']);
    }

    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $details = isset($_POST['message']) ? trim($_POST['message']) : '';

    if ($subject === '' || $details === '') {
        respond(false, ['error' => 'Subject and complaint details are required']);
    }

    // Use session resident_id when available, otherwise fallback to 1 for testing
    $resident_id = isset($_SESSION['resident_id']) ? $_SESSION['resident_id'] : 1;

    // Insert complaint
    $stmt = $pdo->prepare('INSERT INTO complaints (resident_id, subject, details, status) VALUES (:resident_id, :subject, :details, :status)');
    $ok = $stmt->execute([
        ':resident_id' => $resident_id,
        ':subject' => $subject,
        ':details' => $details,
        ':status' => 'Open'
    ]);

    if (!$ok) {
        respond(false, ['error' => 'Failed to insert complaint']);
    }

    $complaint_id = $pdo->lastInsertId();

    // Return inserted data (no join to residents table to avoid missing-table errors)
    respond(true, [
        'id' => $complaint_id,
        'name' => 'Resident #' . $resident_id,
        'subject' => $subject,
        'message' => $details,
        'status' => 'Open'
    ]);

} catch (PDOException $e) {
    error_log('DB error submit_complaint: ' . $e->getMessage());
    respond(false, ['error' => 'Database error', 'details' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Error submit_complaint: ' . $e->getMessage());
    respond(false, ['error' => 'Server error', 'details' => $e->getMessage()]);
}
