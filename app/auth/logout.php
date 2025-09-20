<?php
try {
    $db = new \WireGuardAdmin\Database();
    $auth = new \WireGuardAdmin\Auth($db);
    // Perform logout
    $auth->logout();
    // Redirect to login page with success message
    header('Location: login?success=Logged out successfully');
    exit;
} catch (Exception $e) {
    // If there's an error, still try to clear session and redirect
    session_start();
    session_unset();
    session_destroy();

    header('Location: login?error=An error occurred during logout');
    exit;
}
