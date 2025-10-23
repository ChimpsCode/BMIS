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

// handle send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to']) && isset($_POST['message'])) {
    $senderRole = isset($_SESSION['role']) ? $_SESSION['role'] : $role;
    $toRaw = $_POST['to'];
    // Routing rules:
    // - If sender is resident, route message to 'Barangay Kauswagan' (a central recipient/admin group)
    // - Otherwise use the chosen recipient (admin/staff/resident or a specific name)
    if ($senderRole === 'resident') {
        $to = 'Barangay Kauswagan';
    } else {
        $to = $toRaw;
    }

    $new = [
        'id' => uniqid('msg_'),
        'from' => $userName,
        'from_role' => $senderRole,
        'to' => $to,
        'subject' => trim($_POST['subject']),
        'message' => trim($_POST['message']),
        'created_at' => date('Y-m-d H:i:s')
    ];
    $messages[] = $new;
    file_put_contents($dataFile, json_encode($messages, JSON_PRETTY_PRINT));
    header('Location: messages.php');
    exit;
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

                    // Filter messages based on rules:
                    // - Admin/Staff see messages addressed to their role, messages addressed to 'Barangay Kauswagan', and messages they sent/received.
                    // - Residents see messages addressed to 'resident' and messages they sent/received (they should NOT see admin->staff messages).
                    $visible = array_filter($messages, function($m) use ($viewerRole, $viewerName) {
                        // always show messages sent by the viewer
                        if (isset($m['from']) && $m['from'] === $viewerName) return true;
                        // show messages explicitly addressed to the viewer by name
                        if (isset($m['to']) && $m['to'] === $viewerName) return true;

                        // Admin/Staff rules
                        if ($viewerRole === 'admin' || $viewerRole === 'staff') {
                            if (isset($m['to']) && ($m['to'] === $viewerRole || $m['to'] === 'Barangay Kauswagan' || $m['to'] === 'admin' || $m['to'] === 'staff')) return true;
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

                    $visible = array_reverse($visible);
                    if (empty($visible)) {
                        echo '<div class="muted">No messages yet.</div>';
                    } else {
                        echo '<ul style="list-style:none;padding:0;margin:0">';
                        foreach ($visible as $m) {
                            echo '<div class="message-item">';
                            echo '<div class="message-avatar">' . strtoupper(substr($m['from'],0,1)) . '</div>';
                            echo '<div class="message-content">';
                            echo '<div class="message-subject">' . htmlspecialchars($m['subject'] ?: '(no subject)') . '</div>';
                            echo '<div class="message-meta">From: ' . htmlspecialchars($m['from'] . ' (' . $m['from_role'] . ')') . ' — To: ' . htmlspecialchars($m['to']) . ' · ' . htmlspecialchars($m['created_at']) . '</div>';
                            echo '<div class="message-body">' . nl2br(htmlspecialchars($m['message'])) . '</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
