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
                <p class="muted" style="margin:4px 0 0 0">Requests submitted by residents. Click ⋮ to manage requests.</p>
            </div>
        </div>

        <style>
            .dots-menu {
                font-size: 20px;
                line-height: 1;
                padding: 4px 8px;
                cursor: pointer;
            }
            .status-menu {
                position: absolute;
                right: 0;
                top: 100%;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                z-index: 100;
                min-width: 160px;
            }
            .menu-item {
                display: block;
                width: 100%;
                padding: 8px 12px;
                text-align: left;
                border: none;
                background: none;
                cursor: pointer;
                font-size: 14px;
            }
            .menu-item:hover {
                background: #f3f4f6;
            }
            .text-red {
                color: #dc2626;
            }
            .text-red:hover {
                background: #fee2e2;
            }
        </style>

        <script>
            // Close all menus when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.matches('.dots-menu')) {
                    document.querySelectorAll('.status-menu').forEach(menu => {
                        menu.style.display = 'none';
                    });
                }
            });

            function toggleStatusMenu(event, requestId) {
                event.stopPropagation();
                const menu = document.getElementById('status-menu-' + requestId);
                // Close all other menus
                document.querySelectorAll('.status-menu').forEach(m => {
                    if (m.id !== 'status-menu-' + requestId) {
                        m.style.display = 'none';
                    }
                });
                menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
            }

            function handleReadyPickup(requestId) {
                if (!confirm('Mark this document as ready for pickup?')) return;
                updateRequestStatus(requestId, 'ready_pickup');
            }

            function handleRelease(requestId) {
                if (!confirm('Mark this document as released to resident?')) return;
                updateRequestStatus(requestId, 'release');
            }

            function handleReject(requestId) {
                let reason = prompt('Enter reason for rejecting this request:');
                if (reason === null) return; // cancelled
                reason = reason.trim();
                if (!reason) {
                    alert('Please provide a reason for rejection.');
                    return;
                }
                updateRequestStatus(requestId, 'reject', reason);
            }

            function updateRequestStatus(requestId, action, reason = null) {
                const data = new FormData();
                data.append('request_id', requestId);
                data.append('action', action);
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

            function deleteRequest(requestId) {
                if (!confirm('Are you sure you want to delete this request? This cannot be undone.')) {
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
                        if (element) element.remove();
                    } else {
                        alert('Failed to delete request: ' + (result.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error deleting request: ' + error.message);
                });
            }
        </script>