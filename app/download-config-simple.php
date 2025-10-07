<?php
require_once __DIR__ . '/../includes/auth.php';

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

// Generate configuration
$config = generateSampleWireGuardConfig($peer_id, $interface);

// Set headers for file download
$filename = "peer_{$peer_id}_wireguard.conf";
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($config));

echo $config;

/**
 * Generate sample WireGuard configuration
 */
function generateSampleWireGuardConfig($peer_id, $interface) {
    // Default server configuration - you can customize these values
    $server_endpoint = "your-server.domain.com";  // Change this to your actual server
    $server_port = "51820";
    $server_public_key = "YOUR_SERVER_PUBLIC_KEY_HERE";  // Replace with actual server public key
    
    // Generate a sample private key (in production, this should be properly generated and stored)
    $private_key = base64_encode(random_bytes(32)) . '=';
    
    // Generate peer IP based on peer ID
    $peer_ip = "10.0.0." . (2 + intval($peer_id)) . "/32";
    $allowed_ips = "10.0.0.0/24";  // Allow access to the entire VPN network
    
    // Generate configuration file content
    $config = "# WireGuard Client Configuration\n";
    $config .= "# Peer ID: {$peer_id}\n";
    $config .= "# Interface: {$interface}\n";
    $config .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
    $config .= "# \n";
    $config .= "# IMPORTANT: Replace the following placeholders with actual values:\n";
    $config .= "# - your-server.domain.com: Your server's public IP or domain\n";
    $config .= "# - YOUR_SERVER_PUBLIC_KEY_HERE: Your server's WireGuard public key\n";
    $config .= "# - Add this peer's public key to your server configuration\n";
    $config .= "\n";
    
    $config .= "[Interface]\n";
    $config .= "PrivateKey = {$private_key}\n";
    $config .= "Address = {$peer_ip}\n";
    $config .= "DNS = 1.1.1.1, 8.8.8.8\n";
    $config .= "\n";
    $config .= "[Peer]\n";
    $config .= "PublicKey = {$server_public_key}\n";
    $config .= "Endpoint = {$server_endpoint}:{$server_port}\n";
    $config .= "AllowedIPs = {$allowed_ips}\n";
    $config .= "PersistentKeepalive = 25\n";
    $config .= "\n";
    $config .= "# To complete the setup:\n";
    $config .= "# 1. Replace the placeholders above with your actual server details\n";
    $config .= "# 2. Generate the public key from the private key above\n";
    $config .= "# 3. Add the public key to your server's peer configuration\n";
    $config .= "# 4. Import this configuration into your WireGuard client\n";
    
    return $config;
}
?>