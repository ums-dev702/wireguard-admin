<?php
/**
 * AJAX endpoint to check if an IP is already in use
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['ip'])) {
    echo json_encode(['success' => false, 'error' => 'IP parameter required']);
    exit;
}

$ip = trim($input['ip']);

if (empty($ip)) {
    echo json_encode(['success' => false, 'error' => 'IP cannot be empty']);
    exit;
}

try {
    // Check if IP is in use
    $inUse = isIPInUse($ip);
    
    echo json_encode([
        'success' => true, 
        'inUse' => $inUse,
        'ip' => $ip
    ]);
    
} catch (Exception $e) {
    error_log("Error checking IP availability: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>