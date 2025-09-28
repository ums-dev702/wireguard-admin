<?php
/**
 * AJAX endpoint to get the next available IP for an interface
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_authenticated']) || $_SESSION['user_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get interface from query parameter
$interface = $_GET['interface'] ?? '';

if (empty($interface)) {
    echo json_encode(['success' => false, 'error' => 'Interface parameter required']);
    exit;
}

try {
    // Get next available IP for this interface
    $nextIP = getNextAvailableIP($interface);
    
    if ($nextIP) {
        echo json_encode(['success' => true, 'ip' => $nextIP]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not generate next available IP']);
    }
    
} catch (Exception $e) {
    error_log("Error getting next available IP: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>