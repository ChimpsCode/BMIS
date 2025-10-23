<?php
$role = isset($_GET['role']) ? $_GET['role'] : 'resident';
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
        </div>

        <div style="margin-top:12px">
            <?php
            // Sample complaints - replace with DB query in production
            $complaints = [
                ['name'=>'Juan Dela Cruz','subject'=>'Road lighting issue','message'=>'The street light outside my house is broken for 2 weeks.','date'=>'2025-10-02','status'=>'Open'],
                ['name'=>'Anna Santos','subject'=>'Garbage collection','message'=>'Trash not collected on schedule.','date'=>'2025-10-01','status'=>'In Progress'],
                ['name'=>'Pedro Reyes','subject'=>'Noise complaint','message'=>'Loud music until late at night.','date'=>'2025-09-30','status'=>'Resolved']
            ];

            if (empty($complaints)) {
                echo '<div class="muted">No complaints or feedback submitted yet.</div>';
            } else {
                echo '<div class="complaint-list">';
                foreach($complaints as $c){
                    ?>
                    <div class="complaint-card">
                        <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
                            <div>
                                <div style="font-weight:700"><?php echo htmlspecialchars($c['name']); ?></div>
                                <div class="small muted"><?php echo htmlspecialchars($c['subject']); ?> · <?php echo htmlspecialchars($c['date']); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="small muted">Status</div>
                                <div style="font-weight:700;color:var(--accent)"><?php echo htmlspecialchars($c['status']); ?></div>
                            </div>
                        </div>
                        <div style="margin-top:8px;color:var(--muted)"><?php echo nl2br(htmlspecialchars($c['message'])); ?></div>
                        <div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end">
                            <a href="#" class="btn ghost">Reply</a>
                            <a href="#" class="btn">Mark Resolved</a>
                        </div>
                    </div>
                    <?php
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>
</main>
