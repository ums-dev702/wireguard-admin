<?php
session_start();
require_once __DIR__ . '/../config.php';

function is_authenticated() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        return false;
    }
    
    // Initialize last_activity if not set
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    // Check session timeout
    if (defined('SESSION_TIMEOUT') && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

function login($username, $password) {
    if (defined('ADMIN_USER') && defined('ADMIN_PASS')) {
        if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS)) {
            $_SESSION['authenticated'] = true;
            $_SESSION['last_activity'] = time();
            return true;
        }
    }
    
    return false;
}

function logout() {
    session_unset();
    session_destroy();
}
?>