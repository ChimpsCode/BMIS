<?php
$role = isset($_GET['role']) ? $_GET['role'] : 'resident';
// Ensure session
if (session_status() == PHP_SESSION_NONE) session_start();
// set demo user name based on role for sender identity
$userName = isset($_SESSION['username']) ? $_SESSION['username'] : (isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : ucfirst($role));

// message storage
$dataFile = __DIR__ . '/../data/messages.json';
if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));
$messages = json_decode(file_get_contents($dataFile), true);

// handle send - attempt to store into `tbl_inquiries` when DB available, fallback to JSON demo storage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    // Determine sender role and resident mapping
    $senderRole = isset($_SESSION['role']) ? $_SESSION['role'] : $role;
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $messageBody = trim($_POST['message']);
    $to = isset($_POST['to']) ? trim($_POST['to']) : 'Barangay Kauswagan';

    // Try to insert into database table `tbl_inquiries` if available
    try {
        require_once __DIR__ . '/../includes/db.php';
        // determine resident_id if available
        $resident_id = null;
        if (isset($_SESSION['resident_id'])) {
            $resident_id = (int)$_SESSION['resident_id'];
        } elseif (isset($_SESSION['user_id'])) {
            $u = $pdo->prepare('SELECT resident_id FROM tbl_users WHERE user_id = :uid LIMIT 1');
            $u->execute(['uid' => (int)$_SESSION['user_id']]);
            $r = $u->fetch();
            if ($r && !empty($r['resident_id'])) $resident_id = (int)$r['resident_id'];
        }

        // store intended recipient in the subject as a lightweight tag so we don't need schema changes
        $subWithTo = ($to ? '[to:' . $to . '] ' : '') . $subject;
        $stmt = $pdo->prepare('INSERT INTO tbl_inquiries (resident_id, subject, message, date_sent, status) VALUES (:rid, :sub, :msg, NOW(), :st)');
        $stmt->execute([
            'rid' => $resident_id,
            'sub' => $subWithTo,
            'msg' => $messageBody,
            'st' => 'unread'
        ]);

        // optional: log action
        try {
            $log = $pdo->prepare('INSERT INTO tbl_logs (user_id, activity, action_type) VALUES (:uid, :act, :atype)');
            $log->execute(['uid' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, 'act' => 'New inquiry submitted', 'atype' => 'INSERT']);
        } catch (Exception $e) { /* ignore logging errors */ }

        header('Location: messages.php');
        exit;
    } catch (Exception $e) {
        // DB not available or insert failed — fallback to file-based demo storage
        $to = isset($_POST['to']) ? $_POST['to'] : 'Barangay Kauswagan';
        $new = [
            'id' => uniqid('msg_'),
            'from' => $userName,
            'from_role' => $senderRole,
            'to' => $to,
            'subject' => $subject,
            'message' => $messageBody,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $messages[] = $new;
        file_put_contents($dataFile, json_encode($messages, JSON_PRETTY_PRINT));
        header('Location: messages.php');
        exit;
    }
}

include '../includes/sidebar.php';
include '../includes/header.php';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Messages - Barangay System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<main class="main-content container">
    <div class="card">
        <h2>Messages</h2>
        <p class="muted">Send and view messages between admin, staff, and residents (demo).</p>
        <div class="messages-grid">
            <div class="send-card">
                <h3 style="margin:0 0 8px 0">Send Message</h3>
                <form method="post">
                    <label class="small">To (role/name)</label>
                    <select name="to">
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                        <option value="resident">Resident</option>
                    </select>
                    <label class="small">Subject</label>
                    <input type="text" name="subject" placeholder="Subject">
                    <label class="small">Message</label>
                    <textarea name="message" placeholder="Type your message..."></textarea>
                    <div style="display:flex;gap:8px;justify-content:flex-end">
                        <button class="btn" type="submit">Send</button>
                    </div>
                </form>
            </div>
            <div>
                <h3 style="margin:0 0 8px 0">Received / All Messages</h3>
                <div class="messages-list">
                    <?php
                    // Determine viewer identity and role
                    $viewerRole = isset($_SESSION['role']) ? $_SESSION['role'] : $role;
                    $viewerName = $userName;

                    // If DB is available, also pull inquiries from `tbl_inquiries` and merge into messages list
                    try {
                        if (!isset($pdo)) require_once __DIR__ . '/../includes/db.php';
                        $inqStmt = $pdo->query('SELECT inquiry_id, resident_id, subject, message, date_sent, status FROM tbl_inquiries ORDER BY date_sent DESC LIMIT 100');
                        $inqs = $inqStmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($inqs as $iq) {
                            // resolve resident name if possible
                            $fromName = 'Resident #' . (int)$iq['resident_id'];
                            if (!empty($iq['resident_id'])) {
                                try {
                                    $rstmt = $pdo->prepare('SELECT CONCAT_WS(" ", first_name, middle_name, last_name, suffix) AS full_name FROM tbl_residents WHERE resident_id = :rid LIMIT 1');
                                    $rstmt->execute(['rid' => (int)$iq['resident_id']]);
                                    $rrow = $rstmt->fetch(PDO::FETCH_ASSOC);
                                    if ($rrow && !empty($rrow['full_name'])) $fromName = $rrow['full_name'];
                                } catch (Exception $e) { /* ignore */ }
                            }

                                // Try to extract a [to:role] tag from subject if present
                                $origSub = $iq['subject'] ?? '';
                                $toField = 'Barangay Kauswagan';
                                $cleanSub = $origSub;
                                if (preg_match('/^\s*\[to:([^\]]+)\]\s*(.*)$/i', $origSub, $m)) {
                                    $toField = $m[1];
                                    $cleanSub = $m[2];
                                }

                                $messages[] = [
                                    'id' => 'inq_' . $iq['inquiry_id'],
                                    'from' => $fromName,
                                    'from_role' => 'resident',
                                    'to' => $toField,
                                    'subject' => $cleanSub,
                                    'message' => $iq['message'],
                                    'created_at' => $iq['date_sent'],
                                    'resident_id' => $iq['resident_id']
                                ];
                        }
                    } catch (Exception $e) {
                        // ignore DB read errors; keep file-based messages
                    }

                    // Filter messages based on rules:
                    // - Admin/Staff see messages addressed to their role, messages addressed to 'Barangay Captain', and messages they sent/received.
                    // - Residents see messages addressed to 'resident' and messages they sent/received (they should NOT see admin->staff messages).
                    $visible = array_filter($messages, function($m) use ($viewerRole, $viewerName) {
                        // always show messages sent by the viewer
                        if (isset($m['from']) && $m['from'] === $viewerName) return true;
                        // show messages explicitly addressed to the viewer by name
                        if (isset($m['to']) && $m['to'] === $viewerName) return true;

                        // Admin/Staff rules
                        if ($viewerRole === 'admin' || $viewerRole === 'staff') {
                            if (isset($m['to']) && ($m['to'] === $viewerRole || $m['to'] === 'Barangay Captain' || $m['to'] === 'admin' || $m['to'] === 'staff')) return true;
                            // also show messages where they are sender (already covered) or messages addressed to residents? not needed
                            return false;
                        }

                        // Resident rules
                        if ($viewerRole === 'resident') {
                            if (isset($m['to']) && $m['to'] === 'resident') return true;
                            return false;
                        }

                        // default: hide
                        return false;
                    });

                    // Prepare Sent messages (messages the viewer sent)
                    $sent = array_filter($messages, function($m) use ($viewerName, $viewerRole) {
                        // If the message has resident_id (inquiry), match resident session
                        if (isset($m['resident_id']) && $viewerRole === 'resident' && isset($_SESSION['resident_id'])) {
                            if ((int)$_SESSION['resident_id'] === (int)$m['resident_id']) return true;
                        }
                        // Otherwise match by sender name
                        if (isset($m['from']) && $m['from'] === $viewerName) return true;
                        return false;
                    });

                    // Inbox (visible) and Sent lists
                    $visible = array_reverse($visible);
                    $sent = array_reverse($sent);

                    // Tabs and bulk action controls
                    echo '<div style="display:flex;gap:12px;align-items:center;margin-bottom:8px">';
                    echo '<button id="tab-inbox" class="btn" style="padding:6px 10px">Inbox</button>';
                    echo '<button id="tab-sent" class="btn ghost" style="padding:6px 10px">Sent</button>';
                    echo '</div>';

                    // Inbox list with select-all and bulk delete
                    echo '<div id="inbox-list">';
                    echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">';
                    echo '<label><input type="checkbox" id="select-all-inbox"> Select all</label>';
                    echo '<button id="delete-selected-inbox" class="btn btn-danger" style="margin-left:auto">Delete selected</button>';
                    echo '</div>';
                    if (empty($visible)) {
                        echo '<div class="muted">No messages yet.</div>';
                    } else {
                        echo '<ul style="list-style:none;padding:0;margin:0">';
                        foreach ($visible as $m) {
                            $mid = isset($m['id']) ? $m['id'] : uniqid('msg_');
                            $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $mid);
                            echo '<div class="message-item" data-id="' . htmlspecialchars($mid) . '" id="message-' . $safeId . '" style="display:flex;gap:8px;align-items:flex-start;padding:8px;border-bottom:1px solid #eee">';
                            echo '<input type="checkbox" class="msg-checkbox inbox-checkbox" data-id="' . htmlspecialchars($mid) . '" style="margin-top:6px">';
                            echo '<div style="display:flex;gap:8px;width:100%">';
                            echo '<div class="message-avatar">' . strtoupper(substr(($m['from'] ?? ''),0,1)) . '</div>';
                            // highlight if subject indicates complaint/feedback
                            $subj = $m['subject'] ?? '';
                            $label = '';
                            if (stripos($subj, '(feedback)') !== false || (stripos($subj, 'feedback') !== false && stripos($subj, 'reply') !== false)) {
                                $label = '<span style="background:#ecfccb;color:#065f46;padding:2px 6px;border-radius:4px;font-size:12px;margin-left:8px">Feedback</span>';
                            } elseif (stripos($subj, '(complaint)') !== false || stripos($subj, 'complaint') !== false) {
                                $label = '<span style="background:#cff4fc;color:#064e3b;padding:2px 6px;border-radius:4px;font-size:12px;margin-left:8px">Complaint</span>';
                            }
                            echo '<div class="message-content">';
                            echo '<div class="message-subject">' . htmlspecialchars($subj ?: '(no subject)') . ' ' . $label . '</div>';
                            echo '<div class="message-meta">From: ' . htmlspecialchars(($m['from'] ?? '')) . ' — To: ' . htmlspecialchars($m['to'] ?? '') . ' · ' . htmlspecialchars($m['created_at'] ?? '') . '</div>';
                            echo '<div class="message-body">' . nl2br(htmlspecialchars($m['message'] ?? '')) . '</div>';
                            echo '</div>'; // content
                            echo '</div>'; // flex wrapper
                            echo '</div>';
                        }
                        echo '</ul>';
                    }
                    echo '</div>';

                    // Sent list with select-all and bulk delete
                    echo '<div id="sent-list" style="display:none">';
                    echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">';
                    echo '<label><input type="checkbox" id="select-all-sent"> Select all</label>';
                    echo '<button id="delete-selected-sent" class="btn btn-danger" style="margin-left:auto">Delete selected</button>';
                    echo '</div>';
                    if (empty($sent)) {
                        echo '<div class="muted">No sent messages.</div>';
                    } else {
                        echo '<ul style="list-style:none;padding:0;margin:0">';
                        foreach ($sent as $m) {
                            $mid = isset($m['id']) ? $m['id'] : uniqid('msg_');
                            $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $mid);
                            echo '<div class="message-item" data-id="' . htmlspecialchars($mid) . '" id="message-' . $safeId . '" style="display:flex;gap:8px;align-items:flex-start;padding:8px;border-bottom:1px solid #eee">';
                            echo '<input type="checkbox" class="msg-checkbox sent-checkbox" data-id="' . htmlspecialchars($mid) . '" style="margin-top:6px">';
                            echo '<div style="display:flex;gap:8px;width:100%">';
                            echo '<div class="message-avatar">' . strtoupper(substr(($m['from'] ?? ''),0,1)) . '</div>';
                            // highlight for sent messages as well
                            $subj = $m['subject'] ?? '';
                            $label = '';
                            if (stripos($subj, '(feedback)') !== false || (stripos($subj, 'feedback') !== false && stripos($subj, 'reply') !== false)) {
                                $label = '<span style="background:#ecfccb;color:#065f46;padding:2px 6px;border-radius:4px;font-size:12px;margin-left:8px">Feedback</span>';
                            } elseif (stripos($subj, '(complaint)') !== false || stripos($subj, 'complaint') !== false) {
                                $label = '<span style="background:#cff4fc;color:#064e3b;padding:2px 6px;border-radius:4px;font-size:12px;margin-left:8px">Complaint</span>';
                            }
                            echo '<div class="message-content">';
                            echo '<div class="message-subject">' . htmlspecialchars($subj ?: '(no subject)') . ' ' . $label . '</div>';
                            echo '<div class="message-meta">To: ' . htmlspecialchars($m['to'] ?? '') . ' · ' . htmlspecialchars($m['created_at'] ?? '') . '</div>';
                            echo '<div class="message-body">' . nl2br(htmlspecialchars($m['message'] ?? '')) . '</div>';
                            echo '</div>'; // content
                            echo '</div>'; // flex wrapper
                            echo '</div>';
                        }
                        echo '</ul>';
                    }
                    echo '</div>';

                    // JS: tab toggles, select-all, bulk delete
                    echo "<script>
                        // Tab toggles
                        document.getElementById('tab-inbox').addEventListener('click', function(){
                            document.getElementById('inbox-list').style.display='block';
                            document.getElementById('sent-list').style.display='none';
                            this.classList.remove('ghost');
                            document.getElementById('tab-sent').classList.add('ghost');
                        });
                        document.getElementById('tab-sent').addEventListener('click', function(){
                            document.getElementById('inbox-list').style.display='none';
                            document.getElementById('sent-list').style.display='block';
                            this.classList.remove('ghost');
                            document.getElementById('tab-inbox').classList.add('ghost');
                        });

                        // Select all handlers
                        document.getElementById('select-all-inbox')?.addEventListener('change', function(e){
                            document.querySelectorAll('.inbox-checkbox').forEach(function(cb){ cb.checked = e.target.checked; });
                        });
                        document.getElementById('select-all-sent')?.addEventListener('change', function(e){
                            document.querySelectorAll('.sent-checkbox').forEach(function(cb){ cb.checked = e.target.checked; });
                        });

                        function collectSelectedIds(listClass){
                            var ids = [];
                            document.querySelectorAll('.' + listClass + ':checked').forEach(function(cb){ ids.push(cb.getAttribute('data-id')); });
                            return ids;
                        }

                        function handleBulkDelete(tab){
                            var listClass = (tab === 'inbox') ? 'inbox-checkbox' : 'sent-checkbox';
                            var ids = collectSelectedIds(listClass);
                            if (!ids.length) { alert('No messages selected.'); return; }
                            if (!confirm('Delete ' + ids.length + ' selected message(s)? This cannot be undone.')) return;

                            var fd = new FormData();
                            ids.forEach(function(i){ fd.append('ids[]', i); });
                            fd.append('tab', tab);

                            fetch('delete_inquiries.php', { method: 'POST', body: fd })
                                .then(r => r.json())
                                .then(res => {
                                    if (res.success) {
                                        var removed = res.removed_ids || [];
                                        removed.forEach(function(rid){
                                            var safe = rid.replace(/[^a-zA-Z0-9_-]/g, '_');
                                            var el = document.getElementById('message-' + safe);
                                            if (el) el.remove();
                                        });
                                        alert('Deleted ' + removed.length + ' message(s).');
                                    } else {
                                        alert('Delete failed: ' + (res.error || 'Unknown'));
                                    }
                                }).catch(e => alert('Error: ' + e.message));
                        }

                        document.getElementById('delete-selected-inbox')?.addEventListener('click', function(){ handleBulkDelete('inbox'); });
                        document.getElementById('delete-selected-sent')?.addEventListener('click', function(){ handleBulkDelete('sent'); });
                    </script>";
                    ?>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
