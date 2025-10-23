<?php
// Helper functions for document requests
require_once __DIR__ . '/session.php';

function insert_request($pdo, $resident_id, $doc_id, $notes = '') {
    $stmt = $pdo->prepare('INSERT INTO tbl_requests (resident_id, doc_id, status, date_requested, notes) VALUES (:rid, :did, :st, NOW(), :notes)');
    $stmt->execute([
        'rid' => $resident_id,
        'did' => $doc_id,
        'st' => 'Pending',
        'notes' => $notes
    ]);
    return $pdo->lastInsertId();
}

function fetch_recent_for_resident($pdo, $resident_id, $limit = 20) {
    $stmt = $pdo->prepare('SELECT r.request_id, d.doc_name, r.status, r.date_requested, r.claim_date, rr.first_name, rr.last_name FROM tbl_requests r LEFT JOIN tbl_documents d ON r.doc_id = d.doc_id LEFT JOIN tbl_residents rr ON r.resident_id = rr.resident_id WHERE r.resident_id = :rid ORDER BY r.date_requested DESC LIMIT :lim');
    $stmt->bindValue(':rid', (int)$resident_id, PDO::PARAM_INT);
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_all_requests($pdo) {
    $stmt = $pdo->prepare('SELECT r.request_id, d.doc_name, r.status, r.date_requested, r.claim_date, rr.first_name, rr.last_name FROM tbl_requests r LEFT JOIN tbl_documents d ON r.doc_id = d.doc_id LEFT JOIN tbl_residents rr ON r.resident_id = rr.resident_id ORDER BY r.date_requested DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}
