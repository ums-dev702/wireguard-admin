<?php
/**
 * WireGuard Peer API Endpoint
 * Allows adding peers to WireGuard interfaces via HTTP API
 */

header('Content-Type: application/json');

// Include necessary files
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../autoloader.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enable CORS for API access (adjust as needed for security)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Basic authentication check
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Handle POST requests for adding peers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required parameters
    $interface = $input['interface'] ?? '';
    $public_key = $input['public_key'] ?? '';
    $allowed_ips = $input['allowed_ips'] ?? '';
    
    if (empty($interface) || empty($public_key) || empty($allowed_ips)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: interface, public_key, allowed_ips',
            'required' => ['interface', 'public_key', 'allowed_ips'],
            'provided' => array_keys($input)
        ]);
        exit;
    }
    
    // Validate public key format (basic validation)
    if (strlen($public_key) !== 44 || !preg_match('/^[A-Za-z0-9+\/]+=*$/', $public_key)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid public key format',
            'details' => 'Public key must be 44 characters long and base64 encoded'
        ]);
        exit;
    }
    
    // Validate IP format
    $ip_parts = explode('/', $allowed_ips);
    if (count($ip_parts) !== 2 || !filter_var($ip_parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid allowed_ips format',
            'details' => 'Must be in format: IP/CIDR (e.g., 10.0.0.2/32)'
        ]);
        exit;
    }
    
    try {
        // Add peer to WireGuard interface
        $result = add_peer_to_wireguard_interface($interface, $public_key, $allowed_ips);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'interface' => $result['interface'],
                    'public_key_preview' => $result['public_key_preview'],
                    'allowed_ips' => $result['allowed_ips'],
                    'command_executed' => $result['command']
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $result['message'],
                'command_attempted' => $result['command'],
                'error_output' => $result['error_output'] ?? null
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle GET requests for API documentation
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'api' => 'WireGuard Peer Management API',
        'version' => '1.0',
        'endpoints' => [
            'POST /api/add_peer' => [
                'description' => 'Add a peer to WireGuard interface',
                'parameters' => [
                    'interface' => 'WireGuard interface name (e.g., "acs", "wg0")',
                    'public_key' => 'Base64 encoded WireGuard public key (44 chars)',
                    'allowed_ips' => 'IP/CIDR format (e.g., "10.0.0.2/32")'
                ],
                'example' => [
                    'interface' => 'acs',
                    'public_key' => 'uKJVHa0NZoI9q3Jp6IHB4QrldrYftDMOW4db1+s5f2k=',
                    'allowed_ips' => '10.7.0.5/32'
                ]
            ]
        ],
        'authentication' => 'Session-based authentication required',
        'commands_executed' => [
            'sudo wg set wg_INTERFACE peer PUBLIC_KEY allowed-ips ALLOWED_IPS',
            'sudo wg-quick save wg_INTERFACE'
        ]
    ]);
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed',
    'allowed_methods' => ['GET', 'POST', 'OPTIONS']
]);
?>