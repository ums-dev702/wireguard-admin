<?php
session_start();
require_once __DIR__ . '/../config.php';

function is_authenticated() {
    if (!isset($_SESSION['authenticated'])) {
        return false;
    }
    
    // Check session timeout
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

function login($username, $password) {
    global $admin_user, $admin_pass;
    
    if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    return false;
}

function logout() {
    session_unset();
    session_destroy();
}
?>