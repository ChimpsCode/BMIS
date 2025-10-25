<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) session_start();

// Include database connection
require_once __DIR__ . '/../includes/db.php';

$currentRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'resident';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo $currentRole === 'resident' ? 'Submit Inquiry' : 'View Inquiries'; ?> - Barangay System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Center the main content */
        .main-content {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: calc(100vh - 80px);
            padding: 2rem;
        }

        /* Card styling */
        .inquiry-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 600px;
            padding: 2rem;
            margin: 0 auto;
        }

        /* Form styling */
        .inquiry-form {
            margin-top: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* Submit button */
        .submit-btn {
            background: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .submit-btn:hover {
            background: #2563eb;
        }

        .submit-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        /* Header styling */
        .inquiry-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .inquiry-header h2 {
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .inquiry-header p {
            color: #6b7280;
            font-size: 0.95rem;
        }

        /* Previous inquiries section */
        .previous-inquiries {
            margin-top: 2rem;
        }

        .inquiry-list {
            max-height: 600px;
            overflow-y: auto;
            margin-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .inquiry-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .inquiry-content {
            margin-bottom: 0.5rem;
        }

        .inquiry-meta {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .inquiry-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Admin/Staff specific styles */
        .filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            margin-right: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .filter-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .inquiry-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .inquiry-message {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            margin: 0.5rem 0;
        }

        .inquiry-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .read-btn {
            background: #e0e7ff;
            color: #3730a3;
            border: none;
        }

        .read-btn:hover {
            background: #c7d2fe;
        }

        .reply-btn {
            background: #3b82f6;
            color: white;
            border: none;
        }

        .reply-btn:hover {
            background: #2563eb;
        }

        .reply-form {
            margin-top: 1rem;
            display: none;
        }

        .reply-textarea {
            width: 100%;
            min-height: 100px;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            resize: vertical;
        }

        .reply-actions {
            display: flex;
            justify-content: flex-end;
        }

        .reply-content {
            margin-top: 1rem;
            padding: 1rem;
            background: #f0fdf4;
            border-radius: 6px;
        }

        .reply-header {
            font-weight: 500;
            color: #065f46;
            margin-bottom: 0.5rem;
        }

        .no-inquiries {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }

        .status-unread { background: #fee2e2; color: #991b1b; }
        .status-read { background: #e0e7ff; color: #3730a3; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-replied { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <main class="main-content container">
        <?php if ($currentRole === 'resident'): ?>
            <div class="inquiry-card">
                <div class="inquiry-header">
                    <h2>Submit an Inquiry</h2>
                    <p>Send your questions or concerns directly to the barangay officials.</p>
                </div>
                <form method="post" action="submit_inquiry.php" class="inquiry-form" id="inquiryForm">
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" placeholder="What is your inquiry about?" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" placeholder="Type your message here..." required></textarea>
                    </div>
                    <button type="submit" class="submit-btn">Submit Inquiry</button>
                </form>

                <h3 style="margin-top: 24px;">Your Previous Inquiries</h3>
                <div class="inquiry-list">
                    <?php
                    try {
                        $stmt = $pdo->prepare('
                            SELECT i.*, u.username as staff_name 
                            FROM tbl_inquiries i
                            LEFT JOIN tbl_users u ON u.user_id = i.staff_id 
                            WHERE i.resident_id = :rid 
                            ORDER BY i.date_sent DESC
                        ');
                        $stmt->execute([':rid' => $_SESSION['resident_id']]);
                        
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $date = date('M j, Y g:i A', strtotime($row['date_sent']));
                            echo '<div class="inquiry-item">';
                            echo '<div class="inquiry-content">';
                            echo '<strong>' . htmlspecialchars($row['subject']) . '</strong>';
                            echo '<div>' . nl2br(htmlspecialchars($row['message'])) . '</div>';
                            echo '<div class="inquiry-meta">' . $date;
                            if ($row['staff_name']) {
                                echo ' · Handled by: ' . htmlspecialchars($row['staff_name']);
                            }
                            echo '</div>';
                            echo '</div>';
                            echo '<span class="inquiry-status status-' . $row['status'] . '">' 
                                 . ucfirst($row['status']) . '</span>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="muted">Unable to load your inquiries.</div>';
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="inquiry-card">
                    <div class="inquiry-header">
                        <h2>Resident Inquiries</h2>
                        <p>View and respond to resident inquiries.</p>
                    </div>
                    <div class="inquiry-list">
                        <?php
                        try {
                            $stmt = $pdo->prepare('
                                SELECT i.*, 
                                       r.first_name, r.last_name,
                                       u.username as staff_name,
                                       u.role as staff_role
                                FROM tbl_inquiries i
                                LEFT JOIN tbl_residents r ON i.resident_id = r.resident_id
                                LEFT JOIN tbl_users u ON u.user_id = i.staff_id
                                ORDER BY 
                                    CASE i.status
                                        WHEN "unread" THEN 1
                                        WHEN "read" THEN 2
                                        WHEN "replied" THEN 3
                                        ELSE 4
                                    END,
                                    i.date_sent DESC
                            ');
                            $stmt->execute();
                            $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (empty($inquiries)) {
                                echo '<div class="no-inquiries">No inquiries found.</div>';
                            } else {
                                foreach ($inquiries as $inquiry) {
                                    $date = date('F j, Y g:i A', strtotime($inquiry['date_sent']));
                                    $resident = htmlspecialchars($inquiry['first_name'] . ' ' . $inquiry['last_name']);
                                    ?>
                                    <div class="inquiry-item" data-id="<?php echo $inquiry['inquiry_id']; ?>">
                                        <div class="inquiry-content">
                                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                                <div>
                                                    <span style="font-weight: 500; color: #1f2937;"><?php echo $resident; ?></span>
                                                    <span style="color: #6b7280"> · <?php echo $date; ?></span>
                                                </div>
                                                <span class="inquiry-status status-<?php echo $inquiry['status']; ?>">
                                                    <?php echo ucfirst($inquiry['status']); ?>
                                                </span>
                                            </div>
                                            <h3 style="margin: 0.5rem 0; color: #1f2937;">
                                                <?php echo htmlspecialchars($inquiry['subject']); ?>
                                            </h3>
                                            <div class="inquiry-message">
                                                <?php echo nl2br(htmlspecialchars($inquiry['message'])); ?>
                                            </div>
                                            <?php if (!empty($inquiry['reply'])): ?>
                                                <div class="reply-content">
                                                    <div class="reply-header">
                                                        Reply from <?php echo htmlspecialchars($inquiry['staff_name']); ?>
                                                    </div>
                                                    <?php echo nl2br(htmlspecialchars($inquiry['reply'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($inquiry['status'] !== 'replied'): ?>
                                                <button onclick="showReplyForm(<?php echo $inquiry['inquiry_id']; ?>)" class="btn-reply" style="margin-top: 1rem;">
                                                    Reply
                                                </button>
                                                <div id="replyForm-<?php echo $inquiry['inquiry_id']; ?>" class="reply-form">
                                                    <textarea class="reply-textarea" placeholder="Type your reply..."></textarea>
                                                    <button onclick="sendReply(<?php echo $inquiry['inquiry_id']; ?>)" class="btn-reply">
                                                        Send Reply
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                        } catch (Exception $e) {
                            echo '<div class="no-inquiries">Error loading inquiries: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            error_log('Error in inquiries.php: ' . $e->getMessage());
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    // For residents: Handle inquiry submission
    document.getElementById('inquiryForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);

        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                form.reset();
                alert('Your inquiry has been submitted successfully!');
                if (data.updateStats) {
                    document.dispatchEvent(new Event('updateDashboardStats'));
                }
                location.reload(); // Refresh to show the new inquiry
            } else {
                throw new Error(data.error || 'Failed to submit inquiry');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error submitting inquiry: ' + error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Inquiry';
        });
    });

    // For admin/staff: Handle replies
    function showReplyForm(id) {
        document.querySelectorAll('.reply-form').forEach(form => form.style.display = 'none');
        document.getElementById('replyForm-' + id).style.display = 'block';
    }

    function sendReply(inquiryId) {
        const form = document.getElementById('replyForm-' + inquiryId);
        const textarea = form.querySelector('textarea');
        const sendBtn = form.querySelector('button');
        const reply = textarea.value.trim();
        
        if (!reply) {
            alert('Please enter a reply message');
            return;
        }

        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending...';

        fetch('reply_inquiry.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                inquiry_id: inquiryId,
                reply: reply
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reply sent successfully!');
                location.reload(); // Refresh to show the updated status
            } else {
                throw new Error(data.error || 'Failed to send reply');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error sending reply: ' + error.message);
        })
        .finally(() => {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send Reply';
        });
    }
    // For admin/staff: Filter inquiries
    function filterInquiries(status) {
        // Update active filter button
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');

        // Show/hide inquiries based on status
        document.querySelectorAll('.inquiry-item').forEach(item => {
            if (status === 'all' || item.dataset.status === status) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // For admin/staff: Mark inquiry as read
    function markAsRead(inquiryId) {
        fetch('update_inquiry_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                inquiry_id: inquiryId,
                status: 'read'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = document.querySelector(`[data-id="${inquiryId}"]`);
                item.querySelector('.inquiry-status').className = 'inquiry-status status-read';
                item.querySelector('.inquiry-status').textContent = 'Read';
                item.dataset.status = 'read';
            } else {
                throw new Error(data.error || 'Failed to update status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating status: ' + error.message);
        });
    }

    </script>
</body>
</html>