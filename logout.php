<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';

try {
    $db = new \WireGuardAdmin\Database();
    $auth = new \WireGuardAdmin\Auth($db);
    
    // Perform logout
    $auth->logout();
    
    // Redirect to login page with success message
    header('Location: login.php?success=Logged out successfully');
    exit;
    
} catch (Exception $e) {
    // If there's an error, still try to clear session and redirect
    session_start();
    session_unset();
    session_destroy();
    
    header('Location: login.php?error=An error occurred during logout');
    exit;
}
