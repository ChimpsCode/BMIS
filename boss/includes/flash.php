<?php
// Flash message helper
if (session_status() == PHP_SESSION_NONE) session_start();

function flash_set($msg) {
    $_SESSION['flash'] = $msg;
}

function flash_get() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['flash'])) {
        $m = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $m;
    }
    return null;
}
