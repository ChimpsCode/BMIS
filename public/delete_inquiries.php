<?php
if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$ids = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
}
if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No ids provided.']);
    exit;
}

$viewerRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$viewerName = isset($_SESSION['username']) ? $_SESSION['username'] : (isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : null);
$viewerResident = isset($_SESSION['resident_id']) ? (int)$_SESSION['resident_id'] : null;

$removed = [];

// Attempt to open DB (optional)
$pdo = null;
try {
    require_once __DIR__ . '/../includes/db.php';
} catch (Exception $e) {
    // db not available, we'll only operate on file messages
}

// First handle file-based messages (data/messages.json) by loading, filtering, and saving
$dataFile = __DIR__ . '/../data/messages.json';
$fileMessages = [];
if (file_exists($dataFile)) {
    $fileMessages = json_decode(file_get_contents($dataFile), true) ?: [];
}

// Build a map of file message ids for quick lookup
$fileIndex = [];
foreach ($fileMessages as $i => $fm) {
    if (isset($fm['id'])) $fileIndex[$fm['id']] = $i;
}

// Process DB inquiry ids (inq_123) first
foreach ($ids as $id) {
    if (preg_match('/^inq_(\d+)$/', $id, $m)) {
        $iid = (int)$m[1];
        if ($pdo) {
            try {
                // admin/staff can delete any inquiry; resident only their own
                if ($viewerRole === 'admin' || $viewerRole === 'staff') {
                    $del = $pdo->prepare('DELETE FROM tbl_inquiries WHERE inquiry_id = :id');
                    $del->execute(['id' => $iid]);
                    if ($del->rowCount()) $removed[] = 'inq_' . $iid;
                } else {
                    if ($viewerResident) {
                        $check = $pdo->prepare('SELECT resident_id FROM tbl_inquiries WHERE inquiry_id = :id LIMIT 1');
                        $check->execute(['id' => $iid]);
                        $row = $check->fetch(PDO::FETCH_ASSOC);
                        if ($row && (int)$row['resident_id'] === $viewerResident) {
                            $del = $pdo->prepare('DELETE FROM tbl_inquiries WHERE inquiry_id = :id');
                            $del->execute(['id' => $iid]);
                            if ($del->rowCount()) $removed[] = 'inq_' . $iid;
                        }
                    }
                }
            } catch (Exception $e) {
                // ignore DB errors for now
            }
        }
    }
}

// Now process file messages (msg_ or other ids)
if (!empty($fileMessages) && !empty($ids)) {
    $new = [];
    foreach ($fileMessages as $fm) {
        $keep = true;
        if (isset($fm['id']) && in_array($fm['id'], $ids)) {
            // check permission: admin/staff can delete any file message
            if ($viewerRole === 'admin' || $viewerRole === 'staff') {
                $keep = false;
            } else {
                // resident may delete file messages they sent (match resident_id or from)
                $canDelete = false;
                if (isset($fm['resident_id']) && $viewerResident && (int)$fm['resident_id'] === $viewerResident) $canDelete = true;
                if (!$canDelete && isset($fm['from']) && $viewerName && $fm['from'] === $viewerName) $canDelete = true;
                if ($canDelete) $keep = false;
            }
            if (!$keep) $removed[] = $fm['id'];
        }
        if ($keep) $new[] = $fm;
    }
    // Save only if something removed
    if (count($new) !== count($fileMessages)) {
        file_put_contents($dataFile, json_encode($new, JSON_PRETTY_PRINT));
    }
}

echo json_encode(['success' => true, 'removed_ids' => $removed]);
exit;
