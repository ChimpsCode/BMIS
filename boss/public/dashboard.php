<?php
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
    <?php if ($currentRole === 'admin'): ?>
        <div class="card">
            <?php
            include_once __DIR__ . '/../includes/db.php';
            try {
                // total residents
                $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM tbl_residents');
                $totalResidents = (int)$stmt->fetchColumn();

                // pending requests
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_requests WHERE status = :s");
                $stmt->execute(['s' => 'Pending']);
                $pendingRequests = (int)$stmt->fetchColumn();

                // complaints
                $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM tbl_complaints');
                $complaints = (int)$stmt->fetchColumn();

                // inquiries (approximate by searching messages for 'inquiry' in subject or content)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_messages WHERE subject LIKE :q OR content LIKE :q");
                $q = '%inquiry%';
                $stmt->execute(['q' => $q]);
                $inquiries = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                $totalResidents = 0; $pendingRequests = 0; $complaints = 0; $inquiries = 0;
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
    <?php endif; ?>
    <div class="card">
        <h2>Resident Document Requests</h2>
        <p class="muted">Recent requests and activity. Use the logs panel to trace approvals/deletions/resolutions.</p>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-top:12px">
            <div class="card">
                <h3>Requests</h3>
                <div style="overflow:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Resident</th>
                                <th>Document</th>
                                <th>Status</th>
                                <th>Actioned By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>#REQ-0012</td>
                                <td>Juan Dela Cruz</td>
                                <td>Certification of Residency</td>
                                <td>Approved</td>
                                <td>Admin Maria</td>
                            </tr>
                            <tr>
                                <td>#REQ-0013</td>
                                <td>Anna Santos</td>
                                <td>Barangay Clearance</td>
                                <td>Pending</td>
                                <td>-</td>
                            </tr>
                            <tr>
                                <td>#REQ-0014</td>
                                <td>Pedro Reyes</td>
                                <td>ID Replacement</td>
                                <td>Processed</td>
                                <td>Staff Ramon</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <h3>Activity Logs</h3>
                <div style="padding:8px;margin-top:8px">
                    <ul style="list-style:none;padding:0;margin:0">
                        <li class="small"><strong>Admin Maria</strong> approved <em>#REQ-0012</em> (Certification of Residency) — <span class="muted">2025-10-06 14:22</span></li>
                        <hr />
                        <li class="small"><strong>Staff Ramon</strong> processed <em>#REQ-0014</em> (ID Replacement) — <span class="muted">2025-10-05 09:10</span></li>
                        <hr />
                        <li class="small"><strong>Admin Jose</strong> deleted resident record <em>#RES-047</em> (Ana Lopez) — <span class="muted">2025-09-28 11:03</span></li>
                        <hr />
                        <li class="small"><strong>Staff Claire</strong> marked complaint <em>#CMP-210</em> as resolved — <span class="muted">2025-09-30 16:45</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

</body>
</html>

