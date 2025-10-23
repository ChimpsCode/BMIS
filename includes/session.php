<?php
// Simple session helpers
if (session_status() == PHP_SESSION_NONE) session_start();

function current_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function current_user_id() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_resident_id() {
    return isset($_SESSION['resident_id']) ? (int)$_SESSION['resident_id'] : null;
}

function set_resident_id($id) {
    $_SESSION['resident_id'] = (int)$id;
}
