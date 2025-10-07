<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check authentication
if (!is_authenticated()) {
    http_response_code(401);
    echo "Unauthorized access";
    exit;
}

// Get parameters
$peer_id = $_GET['peer_id'] ?? '';
$interface = $_GET['interface'] ?? '';

if (empty($peer_id) || empty($interface)) {
    http_response_code(400);
    echo "Missing peer_id or interface parameter";
    exit;
}

try {
    $db = get_db();
    
    // Get peer information
    $stmt = $db->prepare('SELECT * FROM wg_peers WHERE id = ?');
    $stmt->execute([$peer_id]);
    $peer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$peer) {
        http_response_code(404);
        echo "Peer not found";
        exit;
    }
    
    // Get interface information
    $interface_name = preg_replace('/^wg_/', '', $interface);
    $stmt = $db->prepare('SELECT * FROM interfaces WHERE name = ?');
    $stmt->execute([$interface_name]);
    $interface_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$interface_info) {
        http_response_code(404);
        echo "Interface not found";
        exit;
    }
    
    // Generate WireGuard client configuration
    $config = generateWireGuardClientConfig($peer, $interface_info, $interface);
    
    // Set headers for file download
    $filename = sanitizeFilename($peer['name'] ?? 'peer') . '_wireguard.conf';
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($config));
    
    echo $config;
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Error generating configuration: " . $e->getMessage();
}

/**
 * Generate WireGuard client configuration
 */
function generateWireGuardClientConfig($peer, $interface_info, $interface_name) {
    $peer_name = $peer['name'] ?? 'Unnamed';
    $peer_allowed_ips = $peer['allowed_ips'] ?? '';
    $peer_private_key = $peer['private_key'] ?? '';
    
    // Interface information
    $interface_address = $interface_info['address'] ?? '10.0.0.1/24';
    $interface_port = $interface_info['port'] ?? '51820';
    $interface_public_key = $interface_info['public_key'] ?? '';
    
    // Get server endpoint
    $server_endpoint = getServerEndpoint();
    
    // If no private key stored, generate one (should be stored in database)
    if (empty($peer_private_key)) {
        $peer_private_key = generatePrivateKey();
        // In production, you should save this to the database
        // updatePeerPrivateKey($peer['id'], $peer_private_key);
    }
    
    // Extract peer IP
    $peer_ip = extract_peer_ip($peer_allowed_ips);
    if ($peer_ip === 'N/A') {
        $peer_ip = '10.0.0.2';
    }
    
    // Parse interface to get network for AllowedIPs
    list($server_ip, $cidr) = explode('/', $interface_address);
    $network_mask = (0xFFFFFFFF << (32 - intval($cidr))) & 0xFFFFFFFF;
    $network = long2ip(ip2long($server_ip) & $network_mask);
    $allowed_ips = $network . '/' . $cidr;
    
    // Generate client configuration
    $config = "# WireGuard Client Configuration\n";
    $config .= "# Peer: {$peer_name}\n";
    $config .= "# Interface: {$interface_name}\n";
    $config .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    $config .= "[Interface]\n";
    $config .= "PrivateKey = {$peer_private_key}\n";
    $config .= "Address = {$peer_allowed_ips}\n";
    $config .= "DNS = 1.1.1.1, 8.8.8.8\n";
    $config .= "\n";
    $config .= "[Peer]\n";
    $config .= "PublicKey = {$interface_public_key}\n";
    $config .= "Endpoint = {$server_endpoint}:{$interface_port}\n";
    $config .= "AllowedIPs = {$allowed_ips}\n";
    $config .= "PersistentKeepalive = 25\n";
    
    return $config;
}

/**
 * Get server endpoint
 */
function getServerEndpoint() {
    // Try to get from config
    if (defined('SERVER_ENDPOINT') && !empty(constant('SERVER_ENDPOINT'))) {
        return constant('SERVER_ENDPOINT');
    }
    
    // Try to get public IP
    $public_ip = getPublicIP();
    if ($public_ip) {
        return $public_ip;
    }
    
    // Fallback to server IP or placeholder
    return $_SERVER['SERVER_ADDR'] ?? $_SERVER['HTTP_HOST'] ?? 'YOUR_SERVER_IP';
}

/**
 * Get public IP
 */
function getPublicIP() {
    $services = [
        'https://api.ipify.org',
        'https://ifconfig.me/ip',
        'https://icanhazip.com'
    ];
    
    foreach ($services as $service) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'WireGuard Admin Config Generator'
                ]
            ]);
            
            $ip = @file_get_contents($service, false, $context);
            if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return trim($ip);
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    return null;
}

/**
 * Generate a private key (simplified - in production, use proper key generation)
 */
function generatePrivateKey() {
    // This is a placeholder - in production, you should generate proper WireGuard keys
    // and store them in the database
    return base64_encode(random_bytes(32)) . '=';
}

/**
 * Sanitize filename for download
 */
function sanitizeFilename($filename) {
    // Remove or replace invalid characters
    $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
    $filename = trim($filename, '_');
    return $filename ?: 'peer';
}

/**
 * Update peer private key in database (for future implementation)
 */
function updatePeerPrivateKey($peer_id, $private_key) {
    try {
        $db = get_db();
        
        // Check if private_key column exists in wg_peers table
        $stmt = $db->prepare("SHOW COLUMNS FROM wg_peers LIKE 'private_key'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Column exists, update it
            $stmt = $db->prepare('UPDATE wg_peers SET private_key = ? WHERE id = ?');
            $stmt->execute([$private_key, $peer_id]);
        }
        // If column doesn't exist, you might want to add it:
        // ALTER TABLE wg_peers ADD COLUMN private_key TEXT;
        
    } catch (Exception $e) {
        error_log("Error updating peer private key: " . $e->getMessage());
    }
}
?>