<?php
/**
 * Check Peer Connection Status API
 * Pings a peer IP to determine if it's online or offline
 */

header('Content-Type: application/json');

// Get peer IP from request
$peer_ip = $_GET['peer_ip'] ?? '';

if (empty($peer_ip)) {
    echo json_encode([
        'success' => false,
        'online' => false,
        'message' => 'No peer IP provided'
    ]);
    exit;
}

// Validate IP format
if (!filter_var($peer_ip, FILTER_VALIDATE_IP)) {
    echo json_encode([
        'success' => false,
        'online' => false,
        'message' => 'Invalid IP address'
    ]);
    exit;
}

try {
    // Use ping command with timeout (1 second, 1 packet)
    // -c 1 = send 1 packet
    // -W 1 = wait max 1 second for response
    // -q = quiet mode (no output)
    $command = sprintf('ping -c 1 -W 1 %s > /dev/null 2>&1', escapeshellarg($peer_ip));
    exec($command, $output, $return_code);
    
    // Return code 0 means ping was successful
    $is_online = ($return_code === 0);
    
    echo json_encode([
        'success' => true,
        'online' => $is_online,
        'peer_ip' => $peer_ip,
        'response_time' => $is_online ? 'Connected' : 'No response'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'online' => false,
        'message' => 'Error checking peer status: ' . $e->getMessage()
    ]);
}
