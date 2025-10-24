<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

if (!isset($_SESSION['resident_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$resident_id = (int)$_SESSION['resident_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the raw POST data for potential JSON input
        $rawInput = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        error_log('Content-Type: ' . $contentType); // Debug log
        error_log('Raw input: ' . $rawInput); // Debug log
        error_log('POST data: ' . print_r($_POST, true)); // Debug log
        
        $pdo->beginTransaction();
        
        if (isset($_POST['delete_all']) && $_POST['delete_all'] === 'true') {
            // Delete all resident's requests
            $stmt = $pdo->prepare('DELETE FROM tbl_requests WHERE resident_id = ?');
            $stmt->execute([$resident_id]);
            $requestCount = $stmt->rowCount();
            
            // Soft delete all resident's complaints
            $stmt = $pdo->prepare('UPDATE complaints SET soft_delete = 1 WHERE resident_id = ?');
            $stmt->execute([$resident_id]);
            $complaintCount = $stmt->rowCount();
            
            $message = "Deleted {$requestCount} requests and archived {$complaintCount} complaints";
            
        } else if (isset($_POST['activities']) && is_string($_POST['activities'])) {
            $activities = json_decode($_POST['activities'], true);
            if (!is_array($activities)) {
                throw new Exception('Invalid activities data');
            }
            
            $requestCount = 0;
            $complaintCount = 0;
            
            foreach ($activities as $activity) {
                if (!isset($activity['id']) || !isset($activity['type'])) {
                    continue;
                }
                
                $id = (int)$activity['id'];
                if ($activity['type'] === 'document') {
                    // Delete document request
                    $stmt = $pdo->prepare('DELETE FROM tbl_requests WHERE request_id = ? AND resident_id = ?');
                    $stmt->execute([$id, $resident_id]);
                    $requestCount += $stmt->rowCount();
                } else if ($activity['type'] === 'complaint') {
                    // Soft delete complaint
                    $stmt = $pdo->prepare('UPDATE complaints SET soft_delete = 1 WHERE id = ? AND resident_id = ?');
                    $stmt->execute([$id, $resident_id]);
                    $complaintCount += $stmt->rowCount();
                }
            }
            
            $message = "Deleted {$requestCount} requests and archived {$complaintCount} complaints";
        } else {
            throw new Exception('Invalid request');
        }
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]);
}