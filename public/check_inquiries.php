<?php
require_once __DIR__ . '/../includes/db.php';

try {
    // Create the tbl_inquiries table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_inquiries (
            inquiry_id INT AUTO_INCREMENT PRIMARY KEY,
            resident_id INT,
            subject VARCHAR(255),
            message TEXT,
            date_sent DATETIME,
            status VARCHAR(50) DEFAULT 'unread',
            staff_id INT,
            FOREIGN KEY (resident_id) REFERENCES tbl_residents(resident_id),
            FOREIGN KEY (staff_id) REFERENCES tbl_users(user_id)
        )
    ");

    // Test query to fetch all inquiries
    $stmt = $pdo->query("
        SELECT 
            i.*,
            r.first_name,
            r.last_name,
            u.username as staff_name
        FROM tbl_inquiries i
        LEFT JOIN tbl_residents r ON i.resident_id = r.resident_id
        LEFT JOIN tbl_users u ON u.user_id = i.staff_id
        ORDER BY i.date_sent DESC
    ");

    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($inquiries);
    echo "</pre>";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>