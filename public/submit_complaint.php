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
    // optional type: 'complaint' or 'feedback'
    $type = isset($_POST['type']) ? trim($_POST['type']) : 'complaint';

    if ($subject === '' || $details === '') {
        respond(false, ['error' => 'Subject and complaint details are required']);
    }

    // Use session resident_id when available, otherwise fallback to 1 for testing
    $resident_id = isset($_SESSION['resident_id']) ? $_SESSION['resident_id'] : 1;

    // Ensure complaints table has a `type` column; add if missing
    try {
        $check = $pdo->query("SHOW COLUMNS FROM complaints LIKE 'type'");
        $hasType = (bool)$check->fetch();
        if (!$hasType) {
            $pdo->exec("ALTER TABLE complaints ADD COLUMN `type` VARCHAR(30) DEFAULT 'complaint'");
        }
    } catch (Exception $e) {
        // ignore if alter fails; insertion will still try without type
    }

    // Insert complaint/feedback
    if (isset($hasType) && $hasType !== false) {
        $stmt = $pdo->prepare('INSERT INTO complaints (resident_id, subject, details, status, `type`) VALUES (:resident_id, :subject, :details, :status, :type)');
        $ok = $stmt->execute([
            ':resident_id' => $resident_id,
            ':subject' => $subject,
            ':details' => $details,
            ':status' => 'Open',
            ':type' => $type
        ]);
    } else {
        // fallback if type column couldn't be added or detected
        $stmt = $pdo->prepare('INSERT INTO complaints (resident_id, subject, details, status) VALUES (:resident_id, :subject, :details, :status)');
        $ok = $stmt->execute([
            ':resident_id' => $resident_id,
            ':subject' => $subject,
            ':details' => $details,
            ':status' => 'Open'
        ]);
    }

    if (!$ok) {
        respond(false, ['error' => 'Failed to insert complaint']);
    }

    $complaint_id = $pdo->lastInsertId();

    // Try to get resident full name from tbl_residents
    try {
        $rstmt = $pdo->prepare('SELECT CONCAT_WS(" ", first_name, middle_name, last_name, suffix) AS full_name FROM tbl_residents WHERE resident_id = :rid LIMIT 1');
        $rstmt->execute([':rid' => $resident_id]);
        $rrow = $rstmt->fetch(PDO::FETCH_ASSOC);
        $resident_name = $rrow && !empty($rrow['full_name']) ? $rrow['full_name'] : ('Resident #' . $resident_id);
    } catch (Exception $e) {
        $resident_name = 'Resident #' . $resident_id;
    }

    respond(true, [
        'id' => $complaint_id,
        'name' => $resident_name,
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
