<?php
$role = isset($_GET['role']) ? $_GET['role'] : 'resident';
include '../includes/sidebar.php';
include '../includes/header.php';
?>
<main class="main-content container">
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
            <div>
                <h2 style="margin-left:100">Document Requests</h2>
                <p class="muted" style="margin:4px 0 0 0">Requests submitted by residents. Click a request to view or process.</p>
            </div>
        </div>
        

        <div style="margin-top:12px">
            <?php
            include_once __DIR__ . '/../includes/db.php';
            include_once __DIR__ . '/../includes/session.php';
            include_once __DIR__ . '/../includes/flash.php';
            include_once __DIR__ . '/../includes/requests_helper.php';

            // determine viewer role early so we can conditionally show the form and lists
            $viewerRole = current_role() ? current_role() : $role;
            $flash = flash_get();
            if ($flash) echo '<div class="card" style="background:#ecfdf5;border:1px solid #bbf7d0;color:#065f46;margin-bottom:12px">' . htmlspecialchars($flash) . '</div>';

            // Handle admin/staff update actions (status changes)
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
                $reqId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
                $newStatus = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
                $claimDate = isset($_POST['claim_date']) && $_POST['claim_date'] !== '' ? $_POST['claim_date'] : null;
                if (session_status() == PHP_SESSION_NONE) session_start();
                $handledBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

                if ($reqId && $newStatus !== '') {
                    try {
                        $upd = $pdo->prepare('UPDATE tbl_requests SET status = :st, handled_by = :hb, claim_date = :cd WHERE request_id = :id');
                        $upd->execute(['st' => $newStatus, 'hb' => $handledBy, 'cd' => $claimDate, 'id' => $reqId]);
                        echo '<div class="card" style="background:#ecfdf5;border:1px solid #bbf7d0;color:#065f46;margin-bottom:12px">Request updated.</div>';
                    } catch (Exception $e) {
                        // if claim_date column missing, attempt to add it and retry once
                        if (strpos($e->getMessage(), 'Unknown column') !== false) {
                            try {
                                $pdo->exec('ALTER TABLE tbl_requests ADD COLUMN claim_date DATETIME NULL');
                                $upd = $pdo->prepare('UPDATE tbl_requests SET status = :st, handled_by = :hb, claim_date = :cd WHERE request_id = :id');
                                $upd->execute(['st' => $newStatus, 'hb' => $handledBy, 'cd' => $claimDate, 'id' => $reqId]);
                                echo '<div class="card" style="background:#ecfdf5;border:1px solid #bbf7d0;color:#065f46;margin-bottom:12px">Request updated.</div>';
                            } catch (Exception $e2) {
                                echo '<div class="card" style="background:#fff3f2;border:1px solid #fecaca;color:#7f1d1d;margin-bottom:12px">Unable to update request: ' . htmlspecialchars($e2->getMessage()) . '</div>';
                            }
                        } else {
                            echo '<div class="card" style="background:#fff3f2;border:1px solid #fecaca;color:#7f1d1d;margin-bottom:12px">Unable to update request: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                }
            }

            // Handle resident form submission (new requests)
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $doc_id = isset($_POST['document_type']) ? (int)$_POST['document_type'] : 0;
                $delivery = isset($_POST['delivery']) ? $_POST['delivery'] : 'pickup';
                $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

                // Prefer session resident_id if available
                $resident_id = null;
                if (session_status() == PHP_SESSION_NONE) session_start();
                if (isset($_SESSION['resident_id'])) {
                    $resident_id = (int)$_SESSION['resident_id'];
                } elseif (isset($_SESSION['user_id'])) {
                    // if users table links to residents, try to fetch
                    try {
                        $s = $pdo->prepare('SELECT resident_id FROM tbl_users WHERE user_id = :uid LIMIT 1');
                        $s->execute(['uid' => (int)$_SESSION['user_id']]);
                        $rrow = $s->fetch();
                        if ($rrow && !empty($rrow['resident_id'])) $resident_id = (int)$rrow['resident_id'];
                    } catch (Exception $e) {
                        // ignore
                    }
                }

                // fallback for demo if still null
                if (!$resident_id) $resident_id = 1;

                if ($doc_id > 0) {
                    // insert request through helper
                    $lastId = insert_request($pdo, $resident_id, $doc_id, $notes);

                    // ensure session resident_id is set
                    if (!current_resident_id()) set_resident_id($resident_id);

                    // set flash and reload so recent list shows the new entry
                    try {
                        $fetch = $pdo->prepare('SELECT r.request_id, d.doc_name FROM tbl_requests r LEFT JOIN tbl_documents d ON r.doc_id = d.doc_id WHERE r.request_id = :id LIMIT 1');
                        $fetch->execute(['id' => $lastId]);
                        $newReq = $fetch->fetch();
                        if ($newReq) flash_set('Request submitted successfully. Request ID: #' . $newReq['request_id'] . ' — ' . $newReq['doc_name']);
                        else flash_set('Request submitted successfully.');
                    } catch (Exception $e) {
                        flash_set('Request submitted successfully.');
                    }

                    echo '<div class="card" style="background:#ecfdf5;border:1px solid #bbf7d0;color:#065f46;margin-bottom:12px">' . htmlspecialchars(flash_get()) . '</div>';
                    echo '<script>setTimeout(function(){ window.location.href = window.location.pathname + window.location.search; }, 500);</script>';

                    // log
                    try {
                        $logStmt = $pdo->prepare('INSERT INTO tbl_logs (user_id, activity) VALUES (:uid, :act)');
                        $logStmt->execute(['uid' => current_user_id(), 'act' => 'New document request by resident_id ' . $resident_id]);
                    } catch (Exception $e) { /* ignore logging errors */ }
                } else {
                    echo '<div class="card" style="background:#fff3f2;border:1px solid #fecaca;color:#7f1d1d;margin-bottom:12px">Please select a document type.</div>';
                }
            }

            // Fetch available documents and fees; if table is empty, insert default document types
            try {
                $docs = [];
                $stmt = $pdo->query('SELECT doc_id, doc_name, fee FROM tbl_documents ORDER BY doc_name');
                $docs = $stmt->fetchAll();

                // If no documents exist, insert sensible defaults and re-query
                if (empty($docs)) {
                    $defaults = [
                        ['name' => 'Brgy Clearance', 'fee' => 50.00],
                        ['name' => 'Certificate of Residency', 'fee' => 30.00],
                        ['name' => 'Certificate of Indigency', 'fee' => 0.00],
                        ['name' => 'Business Permit', 'fee' => 100.00]
                    ];
                    $ins = $pdo->prepare('INSERT INTO tbl_documents (doc_name, description, fee) VALUES (:name, :desc, :fee)');
                    foreach ($defaults as $d) {
                        // check existing by name (defensive)
                        $check = $pdo->prepare('SELECT doc_id FROM tbl_documents WHERE doc_name = :name');
                        $check->execute(['name' => $d['name']]);
                        if (!$check->fetch()) {
                            $ins->execute(['name' => $d['name'], 'desc' => $d['name'], 'fee' => $d['fee']]);
                        }
                    }

                    // re-fetch
                    $stmt = $pdo->query('SELECT doc_id, doc_name, fee FROM tbl_documents ORDER BY doc_name');
                    $docs = $stmt->fetchAll();
                }
            } catch (Exception $e) {
                $docs = [];
            }
            ?>

            <?php
            // GCash payment info (change these values as needed)
            $gcash_name = 'jastin beber';
            $gcash_number = '09166146564';
            ?>

            <?php if ($viewerRole !== 'admin' && $viewerRole !== 'staff'): ?>
            <form method="post" id="requestForm">
                <label for="document_type">Select Document Type</label>
                <select name="document_type" id="document_type">
                    <option value="">-- choose --</option>
                    <?php foreach($docs as $d): ?>
                        <option value="<?php echo (int)$d['doc_id']; ?>" data-fee="<?php echo htmlspecialchars($d['fee']); ?>"><?php echo htmlspecialchars($d['doc_name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <div id="feeBox" style="margin-top:8px;display:none">
                    <div class="small muted">Fee: <strong id="feeAmount"></strong></div>
                    <div class="small muted">Please pay via GCash.</div>
                    <div class="small" style="margin-top:6px">name: <strong><?php echo htmlspecialchars($gcash_name); ?></strong></div>
                    <div class="small">number: <strong><?php echo htmlspecialchars($gcash_number); ?></strong></div>
                </div>

                <div style="margin-top:8px">
                    <label>Delivery Preference</label>
                    <div style="display:flex;gap:8px;margin-top:6px">
                        <label><input type="radio" name="delivery" value="pickup" checked> Pick up</label>
                        <label><input type="radio" name="delivery" value="softcopy"> Soft copy only</label>
                    </div>
                </div>

                <div style="margin-top:8px">
                    <label for="notes">Additional message / purpose</label>
                    <textarea name="notes" id="notes" placeholder="Optional message..."></textarea>
                </div>

                <div style="margin-top:10px">
                    <button class="btn" type="submit">Submit Request</button>
                </div>
            </form>
            <?php else: ?>
                <!-- Admin/Staff not shown the submission form -->
            <?php endif; ?>

            <script>
                // Show fee when doc type selected
                document.getElementById('document_type')?.addEventListener('change', function(e){
                    var opt = e.target.selectedOptions[0];
                    var fee = opt ? opt.getAttribute('data-fee') : null;
                    var box = document.getElementById('feeBox');
                    var amt = document.getElementById('feeAmount');
                    if (fee && fee !== ''){
                        amt.textContent = '₱' + parseFloat(fee).toFixed(2);
                        box.style.display = 'block';
                    } else {
                        box.style.display = 'none';
                        amt.textContent = '';
                    }
                });

                // Handle status updates
                function updateStatus(requestId) {
                    const form = document.getElementById('status-form-' + requestId);
                    if (form) {
                        form.style.display = 'block';
                    }
                }

                function cancelUpdate(requestId) {
                    const form = document.getElementById('status-form-' + requestId);
                    if (form) {
                        form.style.display = 'none';
                        form.querySelector('form').reset();
                    }
                }

                function handleStatusUpdate(event, requestId) {
                    event.preventDefault();
                    const form = event.target;
                    const status = form.querySelector('[name="new_status"]').value;
                        let reason = '';
                        const reasonField = form.querySelector('[name="reason"]');
                    
                        if (status === 'not granted') {
                            reason = reasonField ? reasonField.value : '';
                            if (!reason) {
                                alert('Please provide a reason for not granting the request.');
                                return;
                            }
                            // Format reason if it doesn't follow the template
                            if (!reason.toLowerCase().includes('document is not granted')) {
                                reason = 'Document is not granted (Reason: ' + reason + ')';
                            }
                        }

                    const data = new FormData();
                    data.append('request_id', requestId);
                    data.append('new_status', status);
                        if (reason) {
                        data.append('reason', reason);
                    }

                    fetch('update_request_status.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            window.location.reload();
                        } else {
                            alert('Failed to update status: ' + (result.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        alert('Error updating status: ' + error.message);
                    });
                }

                // Mark as ready for pickup
                function handleReady(requestId) {
                    if (!confirm('Mark this request as ready for pickup?')) return;
                    const data = new FormData();
                    data.append('request_id', requestId);
                    data.append('new_status', 'ready');

                    fetch('update_request_status.php', { method: 'POST', body: data })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) window.location.reload();
                            else alert('Failed to update: ' + (res.error || 'Unknown'));
                        }).catch(e => alert('Error: ' + e.message));
                }

                // Reject with reason
                function handleReject(requestId) {
                    let reason = prompt('Enter rejection reason (e.g. not available):');
                    if (reason === null) return; // cancelled
                    reason = reason.trim();
                    if (!reason) { alert('Reason is required.'); return; }
                    // format reason
                    if (!reason.toLowerCase().includes('rejected')) {
                        reason = 'Rejected (Reason: ' + reason + ')';
                    }

                    const data = new FormData();
                    data.append('request_id', requestId);
                    data.append('new_status', 'rejected');
                    data.append('reason', reason);

                    fetch('update_request_status.php', { method: 'POST', body: data })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) window.location.reload();
                            else alert('Failed to update: ' + (res.error || 'Unknown'));
                        }).catch(e => alert('Error: ' + e.message));
                }

                function deleteRequest(requestId) {
                    if (!confirm('Are you sure you want to delete this request?')) {
                        return;
                    }

                    const data = new FormData();
                    data.append('request_id', requestId);

                    fetch('delete_request.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            const element = document.getElementById('request-' + requestId);
                            if (element) {
                                element.remove();
                            }
                        } else {
                            alert('Failed to delete request: ' + (result.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        alert('Error deleting request: ' + error.message);
                    });
                }

                // Show/hide reason field based on status selection
                document.querySelectorAll('[name="new_status"]').forEach(select => {
                    select.addEventListener('change', function(e) {
                        const reasonField = e.target.closest('form').querySelector('.reason-field');
                        if (e.target.value === 'not granted') {
                            reasonField.style.display = 'block';
                                const textarea = reasonField.querySelector('textarea');
                                textarea.required = true;
                                if (!textarea.value) {
                                    textarea.value = 'Document is not granted (Reason: )';
                                }
                        } else {
                            reasonField.style.display = 'none';
                            reasonField.querySelector('textarea').required = false;
                        }
                    });
                });
            </script>

            <style>
                .btn-small {
                    padding: 4px 8px;
                    font-size: 12px;
                }
                .btn-danger {
                    background-color: #dc2626;
                    color: white;
                }
                .btn-danger:hover {
                    background-color: #b91c1c;
                }
            </style>

            <hr style="margin:18px 0" />

            <h3>Recent Requests</h3>
            <?php
            // Show recent requests (from DB)
            try {
                if ($viewerRole === 'admin' || $viewerRole === 'staff') {
                    // admins and staff see all requests
                    $stmt = $pdo->prepare('SELECT r.request_id, d.doc_name, r.status, r.date_requested, r.reason, rr.first_name, rr.last_name FROM tbl_requests r LEFT JOIN tbl_documents d ON r.doc_id = d.doc_id LEFT JOIN tbl_residents rr ON r.resident_id = rr.resident_id ORDER BY r.date_requested DESC');
                    $stmt->execute();
                } elseif ($viewerRole === 'resident') {
                    // resident - only show their own requests (limit 20)
                    $residentFilter = null;
                    if (isset($_SESSION['resident_id'])) $residentFilter = (int)$_SESSION['resident_id'];
                    elseif (isset($_SESSION['user_id'])) {
                        $u = $pdo->prepare('SELECT resident_id FROM tbl_users WHERE user_id = :uid LIMIT 1');
                        $u->execute(['uid' => (int)$_SESSION['user_id']]);
                        $rr = $u->fetch();
                        if ($rr && !empty($rr['resident_id'])) $residentFilter = (int)$rr['resident_id'];
                    }
                    if ($residentFilter) {
                        // include reason so residents can see denial reasons
                        $stmt = $pdo->prepare('SELECT r.request_id, d.doc_name, r.status, r.date_requested, r.reason, rr.first_name, rr.last_name FROM tbl_requests r LEFT JOIN tbl_documents d ON r.doc_id = d.doc_id LEFT JOIN tbl_residents rr ON r.resident_id = rr.resident_id WHERE r.resident_id = :rid ORDER BY r.date_requested DESC LIMIT 20');
                        $stmt->execute(['rid' => $residentFilter]);
                    } else {
                        // no resident mapping available, return empty
                        $recent = [];
                        $stmt = null;
                    }
                } else {
                    // other roles (guest) - no requests
                    $recent = [];
                    $stmt = null;
                }
                if ($stmt) $recent = $stmt->fetchAll();
            } catch (Exception $e) {
                $recent = [];
            }

            if (empty($recent)){
                echo '<div class="muted">No recent requests.</div>';
            } else {
                echo '<div class="request-list">';
                foreach($recent as $rq){
                    ?>
                    <div class="request-card" id="request-<?php echo (int)$rq['request_id']; ?>">
                        <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
                            <div>
                                <div style="font-weight:700"><?php echo htmlspecialchars(trim($rq['first_name'] . ' ' . $rq['last_name'])); ?></div>
                                <div class="small muted"><?php echo htmlspecialchars($rq['date_requested']); ?> · <strong><?php echo htmlspecialchars($rq['doc_name']); ?></strong></div>
                                    <div class="status-display" style="margin-top:4px">
                                        <?php if ($rq['status'] === 'ready'): ?>
                                            <div class="small" style="color:#059669">Status: Ready for pickup</div>
                                        <?php elseif ($rq['status'] === 'rejected' && !empty($rq['reason'])): ?>
                                            <div class="small" style="color:#991b1b">Status: <?php echo htmlspecialchars($rq['reason']); ?></div>
                                        <?php elseif ($rq['status'] === 'pending'): ?>
                                            <div class="small" style="color:#d97706">Status: Pending</div>
                                        <?php endif; ?>
                                    </div>
                            </div>
                            <div class="text-right">
                                <div class="small muted">Status</div>
                                <div style="font-weight:700;color:var(--accent)"><?php echo htmlspecialchars($rq['status']); ?></div>
                                <?php if ($viewerRole === 'admin' || $viewerRole === 'staff'): ?>
                                    <div style="margin-top:8px;display:flex;gap:4px;">
                                        <button class="btn btn-small" onclick="updateStatus(<?php echo (int)$rq['request_id']; ?>)">Update Status</button>
                                        <button class="btn btn-small btn-danger" onclick="deleteRequest(<?php echo (int)$rq['request_id']; ?>)">Delete</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (($viewerRole === 'admin' || $viewerRole === 'staff') && $rq['status'] === 'pending'): ?>
                        <div class="status-update-form" id="status-form-<?php echo (int)$rq['request_id']; ?>" style="display:none;margin-top:12px">
                            <div style="display:flex;gap:8px;align-items:center">
                                <button class="btn btn-small" onclick="handleReady(<?php echo (int)$rq['request_id']; ?>)">Mark Ready for pickup</button>
                                <button class="btn btn-small btn-warning" onclick="handleReject(<?php echo (int)$rq['request_id']; ?>)">Reject</button>
                                <button class="btn btn-small" onclick="cancelUpdate(<?php echo (int)$rq['request_id']; ?>)">Close</button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>
</main>
