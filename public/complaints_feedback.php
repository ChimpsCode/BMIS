<?php
$role = isset($_GET['role']) ? $_GET['role'] : 'resident';
// Prefer session role when available
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
}
include '../includes/sidebar.php';
include '../includes/header.php';
?>
<main class="main-content container">
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                <div>
                    <h2 style="margin:0">Complaints & Feedback</h2>
                    <p class="muted" style="margin:4px 0 0 0">Resident complaints and feedback are listed below. Staff can respond or mark resolved.</p>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-start">
                    <!-- Action buttons visible to residents for creating items -->
                    <a href="#" class="btn" style="padding:8px 12px;font-size:0.95rem;text-decoration:none">+Complaint</a>
                    <a href="#" class="btn" style="padding:8px 12px;font-size:0.95rem;text-decoration:none">+Feedback</a>
                </div>
            </div>

        <div style="margin-top:12px">
            <?php
            // Load complaints from database
            require_once __DIR__ . '/../includes/db.php';

            // Determine current user (resident) id if available
            $current_resident_id = isset($_SESSION['resident_id']) ? $_SESSION['resident_id'] : null;

            try {
                if ($role === 'resident' && $current_resident_id) {
                    // Resident: only show their own complaints
                    $stmt = $pdo->prepare(
                        'SELECT id AS complaint_id, resident_id, subject, details, status, created_at FROM complaints WHERE resident_id = :rid ORDER BY id DESC'
                    );
                    $stmt->execute([':rid' => $current_resident_id]);
                } else {
                    // Admin/Staff: show all complaints and attempt to fetch resident name if table exists
                    // Use LEFT JOIN on residents if present; if not, fallback to resident_id only
                    try {
                        $stmt = $pdo->query(
                            'SELECT c.id AS complaint_id, c.resident_id, c.subject, c.details, c.status, c.created_at, COALESCE(r.name, NULL) AS resident_name FROM complaints c LEFT JOIN residents r ON c.resident_id = r.resident_id ORDER BY c.id DESC'
                        );
                    } catch (Exception $e) {
                        // Fallback: no residents table
                        $stmt = $pdo->query('SELECT id AS complaint_id, resident_id, subject, details, status, created_at FROM complaints ORDER BY id DESC');
                    }
                }

                $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $complaints = [];
            }

            if (empty($complaints)) {
                echo '<div class="muted">No complaints or feedback submitted yet.</div>';
            } else {
                // Debug: show count of loaded complaints
                echo '<div class="small muted" style="margin-bottom:8px">' . count($complaints) . ' complaint(s) found</div>';
                echo '<div class="complaint-list">';
                foreach($complaints as $c){
                    ?>
                    <div class="complaint-card">
                        <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
                            <div>
                                <div style="font-weight:700">
                                    <?php
                                    if ($role !== 'resident') {
                                        // Admin/Staff: show full name when available
                                        if (!empty($c['resident_name'])) {
                                            echo htmlspecialchars($c['resident_name']);
                                        } else {
                                            echo 'Resident #' . htmlspecialchars($c['resident_id']);
                                        }
                                    } else {
                                        // Resident: do not show name header, show "Your complaint"
                                        echo 'Your complaint';
                                    }
                                    ?>
                                </div>
                                <div class="small muted"><?php echo htmlspecialchars($c['subject']); ?> · <?php echo htmlspecialchars(date('Y-m-d', strtotime($c['created_at']))); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="small muted">Status</div>
                                <div style="font-weight:700;color:var(--accent)"><?php echo htmlspecialchars($c['status']); ?></div>
                            </div>
                        </div>
                        <div style="margin-top:8px;color:var(--muted)"><?php echo nl2br(htmlspecialchars($c['details'])); ?></div>
                        <?php if ($role !== 'resident') { ?>
                        <div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end">
                            <a href="#" class="btn ghost">Reply</a>
                            <a href="#" class="btn">Mark Resolved</a>
                        </div>
                        <?php } ?>
                    </div>
                    <?php
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>
</main>

<!-- Complaint Modal -->
<div id="complaintModal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);z-index:9999">
    <div style="width:520px;max-width:96%;background:var(--card);padding:18px;border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,0.35)">
        <h3 style="margin:0 0 8px 0">New Complaint</h3>
        <form id="complaintForm">
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
                <input type="text" name="subject" id="cf-subject" placeholder="Subject" required />
                <textarea name="message" id="cf-message" placeholder="Describe your complaint" rows="5" required></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                <button type="button" id="cf-cancel" class="btn ghost" style="background:transparent;color:var(--accent);border:1px solid rgba(37,99,235,0.12)">Cancel</button>
                <button type="submit" id="cf-submit" class="btn">Send</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const modal = document.getElementById('complaintModal');
    const openBtn = document.querySelector('.card > div > div > a.btn');
    const form = document.getElementById('complaintForm');
    const list = document.querySelector('.complaint-list');

    if (openBtn) {
        openBtn.addEventListener('click', function(e){
            e.preventDefault();
            modal.style.display = 'flex';
            document.getElementById('cf-subject').focus();
        });
    }

    document.getElementById('cf-cancel').addEventListener('click', function(){
        modal.style.display = 'none';
        form.reset();
    });

    form.addEventListener('submit', async function(e){
        e.preventDefault();
        const submitBtn = document.getElementById('cf-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        const data = new FormData(form);

        try {
            const res = await fetch('submit_complaint.php', { method: 'POST', body: data });
            const json = await res.json();
            if (!json.ok) throw new Error(json.error || 'Failed');

            // Append new complaint to list (if list exists)
            if (list) {
                const c = json;
                const card = document.createElement('div');
                card.className = 'complaint-card';
                card.innerHTML = `
                    <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
                        <div>
                            <div style="font-weight:700">${escapeHtml(c.name)}</div>
                            <div class="small muted">${escapeHtml(c.subject)}</div>
                        </div>
                        <div class="text-right">
                            <div class="small muted">Status</div>
                            <div style="font-weight:700;color:var(--accent)">${escapeHtml(c.status)}</div>
                        </div>
                    </div>
                    <div style="margin-top:8px;color:var(--muted)">${escapeHtml(c.message || c.details).replace(/\n/g, '<br>')}</div>
                `;
                list.insertBefore(card, list.firstChild);
            }

            modal.style.display = 'none';
            form.reset();
        } catch (err) {
            alert('Error: ' + err.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send';
        }
    });

    // Simple HTML escape
    function escapeHtml(s){
        return String(s)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#39;');
    }
});
</script>
