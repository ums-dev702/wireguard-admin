<?php
/**
 * Port Validator API Endpoint
 * Validates if a port is available for WireGuard interface use
 * Checks UFW rules, port forwarding, and socket binding
 */

// Prevent direct access and ensure proper session handling
if (!defined('STDIN') && php_sapi_name() !== 'cli') {
    // This is a web request
    header('Content-Type: application/json');
    
    // Include necessary files
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../autoloader.php';
    require_once __DIR__ . '/../../includes/functions.php';
    
    // Check if user is authenticated (you may want to modify this based on your auth system)
    // For now, we'll do a basic check
    session_start();
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $port = isset($_POST['port']) ? (int)$_POST['port'] : 0;
    
    if ($port <= 0) {
        echo json_encode([
            'valid' => false,
            'message' => 'Invalid port number'
        ]);
        exit;
    }
    
    try {
        $validation = validate_wireguard_port($port);
        echo json_encode($validation);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'valid' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

// Handle GET request (for testing)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['port'])) {
    $port = (int)$_GET['port'];
    
    if ($port <= 0) {
        echo json_encode([
            'valid' => false,
            'message' => 'Invalid port number'
        ]);
        exit;
    }
    
    try {
        $validation = validate_wireguard_port($port);
        echo json_encode($validation);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'valid' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

// Invalid request method
http_response_code(405);
echo json_encode([
    'valid' => false,
    'message' => 'Method not allowed'
]);
?>