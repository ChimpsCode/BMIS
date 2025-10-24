<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) session_start();

// Include database connection early
require_once __DIR__ . '/../includes/db.php';

$role = isset($_GET['role']) ? $_GET['role'] : 'resident'; // For demo, switch role via ?role=admin|staff|resident
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard - Barangay System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/header.php'; ?>

<main class="main-content container">
    <?php
    // Prefer session role if set
    if (session_status() == PHP_SESSION_NONE) session_start();
    $currentRole = isset($_SESSION['role']) ? $_SESSION['role'] : (isset($role) ? $role : 'resident');
    ?>
    <?php if ($currentRole === 'admin' || $currentRole === 'staff'): ?>
        <div class="card">
            <?php
            include_once __DIR__ . '/../includes/db.php';
            try {
                // total residents
                $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM tbl_residents');
                $totalResidents = (int)$stmt->fetchColumn();

                // pending requests
                // Consider requests pending when pending_status is empty or explicitly 'pending',
                // or status indicates pending/processing. This makes the count resilient to
                // different flows that set pending_status/payment_status/status.
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM tbl_requests WHERE (pending_status IS NULL OR TRIM(pending_status) = '' OR LOWER(pending_status) = 'pending') OR LOWER(status) IN ('pending','processing')"
                );
                $stmt->execute();
                $pendingRequests = (int)$stmt->fetchColumn();

                // complaints
                $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM tbl_complaints');
                $complaints = (int)$stmt->fetchColumn();

                // inquiries (best-effort - search messages for the keyword 'inquiry' in subject or content)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_messages WHERE LOWER(subject) LIKE :q OR LOWER(content) LIKE :q");
                $q = '%inquiry%';
                $stmt->execute(['q' => $q]);
                $inquiries = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                // If any query fails, keep any counts that were already populated and
                // default the rest to 0. This prevents a single failing query from
                // zeroing out all stats and makes debugging easier.
                $totalResidents = isset($totalResidents) ? $totalResidents : 0;
                $pendingRequests = isset($pendingRequests) ? $pendingRequests : 0;
                $complaints = isset($complaints) ? $complaints : 0;
                $inquiries = isset($inquiries) ? $inquiries : 0;

                // Log the error for debugging (server error log). Remove or change
                // this in production if you don't want DB errors logged.
                if (function_exists('error_log')) {
                    error_log('[dashboard] stats query error: ' . $e->getMessage());
                }
            }

            ?>
            <div class="stats-box">
                <div class="stat-item">
                    <div class="label">Total Residents</div>
                    <div class="value"><?php echo number_format($totalResidents); ?></div>
                </div>
                <div class="stat-item">
                    <div class="label">Pending Requests</div>
                    <div class="value"><?php echo number_format($pendingRequests); ?></div>
                </div>
                <div class="stat-item">
                    <div class="label">Complaints</div>
                    <div class="value"><?php echo number_format($complaints); ?></div>
                </div>
                <div class="stat-item">
                    <div class="label">Inquiries</div>
                    <div class="value"><?php echo number_format($inquiries); ?></div>
                </div>
            </div>
            <div>
                <h3>Quick Access</h3>
                <div class="quick-grid">
                    <div class="quick-card">
                        <h4>+ Add Resident</h4>
                        <p>Add a new resident record quickly.</p>
                        <div style="margin-top:10px">
                            <a href="resident_record.php" class="btn">Open</a>
                        </div>
                    </div>
                    <div class="quick-card">
                        <h4>Process Request</h4>
                        <p>Open pending document requests and process them.</p>
                        <div style="margin-top:10px">
                            <a href="document_requests.php" class="btn ghost">Open</a>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        <div class="card" style="margin-top:12px">
            <h3>Recent Admin/Staff Logs</h3>
            <?php
            try {
                $logStmt = $pdo->prepare('SELECT l.log_id, l.user_id, l.activity, l.timestamp, l.action_type, u.username FROM tbl_logs l LEFT JOIN tbl_users u ON l.user_id = u.user_id WHERE u.role IN (\'admin\', \'staff\') ORDER BY l.timestamp DESC LIMIT 20');
                $logStmt->execute();
                $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($logs)) {
                    echo '<div class="muted">No recent admin/staff logs.</div>';
                } else {
                    echo '<ul style="list-style:none;padding:0;margin:0">';
                    foreach ($logs as $lg) {
                        $who = $lg['username'] ? htmlspecialchars($lg['username']) : ('User #' . (int)$lg['user_id']);
                        $ts = htmlspecialchars(date('Y-m-d H:i', strtotime($lg['timestamp'])));
                        $atype = $lg['action_type'] ? htmlspecialchars($lg['action_type']) : '';
                        echo '<li style="padding:8px 0;border-bottom:1px solid var(--border)"><strong>' . $who . '</strong> — ' . htmlspecialchars($lg['activity']) . ' <span class="muted">' . $ts . ($atype ? ' · ' . $atype : '') . '</span></li>';
                    }
                    echo '</ul>';
                }
            } catch (Exception $e) {
                echo '<div class="muted">Unable to load logs.</div>';
            }
            ?>
        </div>
    <?php endif; ?>
    <?php if ($currentRole === 'resident'): ?>
        <div class="card">
            <h2>Request a Document</h2>
            <p class="muted">Quickly request common barangay documents.</p>
            <div class="doc-grid">
                <a class="doc-card" href="document_requests.php?type=certificate_of_residency">
                    <h4>Brgy Certificate</h4>
                    <span class="btn">Request</span>
                </a>
                <a class="doc-card" href="document_requests.php?type=barangay_clearance">
                    <h4>Brgy Clearance</h4>
                    <span class="btn">Request</span>
                </a>
                <a class="doc-card" href="document_requests.php?type=cedula">
                    <h4>Brgy Cedula</h4>
                    <span class="btn">Request</span>
                </a>
                <a class="doc-card" href="document_requests.php?type=certificate_of_indigency">
                    <h4>Certificate of Indigency</h4>
                    <span class="btn">Request</span>
                </a>
            </div>
        </div>

        <div class="card">
        <h2>Activity Logs</h2>
        <p class="muted">Track all your activities including document requests, complaints, and feedback.</p>
        <div style="margin-top:12px">
            <?php
            try {
                // Check if we have a valid resident ID from session
                $resident_id = isset($_SESSION['resident_id']) ? (int)$_SESSION['resident_id'] : null;
                
                if ($resident_id && isset($pdo)) {
                    // Fetch document requests
                    $stmt = $pdo->prepare(
                        'SELECT 
                            r.request_id as id,
                            d.doc_name as title,
                            r.status,
                            r.date_requested as date,
                            r.pending_status,
                            r.payment_status,
                            "document" as type
                         FROM tbl_requests r 
                         LEFT JOIN tbl_documents d ON r.doc_id = d.doc_id 
                         WHERE r.resident_id = :rid'
                    );
                    $stmt->execute([':rid' => $resident_id]);
                    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Fetch complaints
                    $stmt = $pdo->prepare(
                        'SELECT 
                            id,
                            subject as title,
                            status,
                            created_at as date,
                            "complaint" as type
                         FROM complaints 
                         WHERE resident_id = :rid 
                         AND (soft_delete = 0 OR soft_delete IS NULL)'
                    );
                    $stmt->execute([':rid' => $resident_id]);
                    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Combine and sort all activities
                    $activities = array_merge($requests, $complaints);
                    usort($activities, function($a, $b) {
                        return strtotime($b['date']) - strtotime($a['date']);
                    });

                    // Keep only the most recent 15 activities
                    $activities = array_slice($activities, 0, 15);

                    if (empty($activities)) {
                        echo '<div class="muted" style="text-align:center;padding:20px">No activities found.</div>';
                    } else {
                        echo '<div class="activity-log" style="max-height:500px;overflow-y:auto">';
                        foreach ($activities as $activity) {
                            $icon = $activity['type'] === 'document' ? '📄' : '💬';
                            $status = isset($activity['pending_status']) && !empty($activity['pending_status']) 
                                    ? $activity['pending_status'] 
                                    : $activity['status'];
                            
                            $statusColor = 'inherit';
                            $statusLower = strtolower($status);
                            if ($statusLower === 'approved' || $statusLower === 'ready for pickup' || $statusLower === 'completed') {
                                $statusColor = '#059669'; // green
                            } elseif ($statusLower === 'pending' || $statusLower === 'processing') {
                                $statusColor = '#d97706'; // amber
                            }

                            $date = date('M j, Y', strtotime($activity['date']));
                            $id = isset($activity['request_id']) 
                                ? '#REQ-' . str_pad($activity['request_id'], 4, '0', STR_PAD_LEFT)
                                : '#' . $activity['id'];
                            
                            $itemId = $activity['type'] === 'document' ? 'request-' . $activity['id'] : 'complaint-' . $activity['id'];
                            
                            echo '<div class="activity-item" id="' . $itemId . '" style="padding:12px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:start">';
                            echo '<div style="font-size:1.5rem">' . $icon . '</div>';
                            echo '<div style="flex:1">';
                            echo '<div style="display:flex;justify-content:space-between;align-items:start;gap:8px">';
                            echo '<div>';
                            echo '<div style="font-weight:500">' . htmlspecialchars($activity['title']) . '</div>';
                            echo '<div class="small muted">' . $date . ' · ' . ucfirst($activity['type']) . ' ' . $id . '</div>';
                            echo '</div>';
                            echo '<div style="display:flex;align-items:center;gap:8px">';
                            echo '<div style="color:' . $statusColor . ';font-weight:500">' . htmlspecialchars($status) . '</div>';
                            
                            // Add delete button based on type, status and viewer role
                            $canDelete = false;
                            $deleteFunction = '';
                            if ($activity['type'] === 'document') {
                                // If admin/staff viewing, allow server-side delete; residents can cancel pending requests
                                if (isset($currentRole) && ($currentRole === 'admin' || $currentRole === 'staff')) {
                                    $canDelete = true;
                                    $deleteFunction = 'adminDeleteRequest(' . $activity['id'] . ')';
                                } else {
                                    $statusLower = strtolower($status);
                                    if ($statusLower === 'pending' || $statusLower === 'processing' || $statusLower === '') {
                                        $canDelete = true;
                                        $deleteFunction = 'cancelRequest(' . $activity['id'] . ')';
                                    }
                                }
                            } else if ($activity['type'] === 'complaint') {
                                // complaints: resident-side hide
                                $canDelete = true;
                                $deleteFunction = 'residentHideComplaint(' . $activity['id'] . ')';
                            }
                            
                            if ($canDelete) {
                                echo '<button onclick="' . $deleteFunction . '" class="btn btn-danger" style="padding:4px 8px;font-size:0.85rem">Delete</button>';
                            }
                            
                            echo '</div>';
                            echo '</div>';

                            if (isset($activity['payment_status']) && $activity['payment_status']) {
                                echo '<div class="small muted" style="margin-top:4px">Payment Status: ' . htmlspecialchars($activity['payment_status']) . '</div>';
                            }
                            echo '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                } else {
                    echo '<div class="muted" style="text-align:center;padding:20px">Please log in to view your document requests.</div>';
                }
            } catch (Exception $e) {
                echo '<div class="muted">Could not load document requests. Please try again later.</div>';
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<script>
// Cancel a pending document request (resident)
function cancelRequest(requestId){
    if (!confirm('Cancel this request? This action cannot be undone.')) return;
    fetch('cancel_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'request_id=' + encodeURIComponent(requestId)
    }).then(r => r.json()).then(res => {
        if (res && res.success) {
            const el = document.getElementById('request-' + requestId);
            if (el) el.remove();
            alert('Request cancelled.');
        } else {
            alert('Unable to cancel: ' + (res && res.error ? res.error : 'Unknown'));
        }
    }).catch(e => alert('Error: ' + e.message));
}

// Resident-side hide for complaints (localStorage)
function residentHideComplaint(complaintId){
    if (!confirm('Delete this complaint from your view? Admins will still see it.')) return;
    try{
        const key = 'hidden_complaints';
        const raw = localStorage.getItem(key);
        const arr = raw ? JSON.parse(raw) : [];
        if (!arr.includes(complaintId)) arr.push(complaintId);
        localStorage.setItem(key, JSON.stringify(arr));
        const el = document.getElementById('complaint-' + complaintId);
        if (el) el.remove();
        alert('Complaint hidden from your view.');
    } catch(e){
        alert('Error hiding complaint: ' + e.message);
    }
}

// On load hide complaints previously hidden by resident
(function(){
    try{
        const raw = localStorage.getItem('hidden_complaints');
        const arr = raw ? JSON.parse(raw) : [];
        arr.forEach(id => {
            const el = document.getElementById('complaint-' + id);
            if (el) el.remove();
        });
    } catch(e){ /* ignore */ }
})();

// Admin/server-side delete for requests
function adminDeleteRequest(requestId) {
    if (!confirm('Delete this request permanently? This cannot be undone.')) return;
    const data = new FormData();
    data.append('request_id', requestId);

    fetch('delete_request.php', {
        method: 'POST',
        body: data
    }).then(r => r.json()).then(res => {
        if (res && res.success) {
            const el = document.getElementById('request-' + requestId);
            if (el) el.remove();
            alert('Request deleted.');
        } else {
            alert('Unable to delete: ' + (res && res.error ? res.error : 'Unknown'));
        }
    }).catch(e => alert('Error: ' + e.message));
}
</script>

</body>
</html>

