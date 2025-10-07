<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Set content type for download
header('Content-Type: text/plain');

// Check authentication
if (!is_authenticated()) {
    http_response_code(401);
    echo "# Error: Unauthorized access\n";
    exit;
}

// Get parameters
$peer_id = $_GET['peer_id'] ?? '';
$interface = $_GET['interface'] ?? '';

if (empty($peer_id) || empty($interface)) {
    http_response_code(400);
    echo "# Error: Missing peer_id or interface parameter\n";
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
        echo "# Error: Peer not found\n";
        exit;
    }
    
    // Get interface information
    $interface_name = preg_replace('/^wg_/', '', $interface);
    $stmt = $db->prepare('SELECT * FROM interfaces WHERE name = ?');
    $stmt->execute([$interface_name]);
    $interface_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$interface_info) {
        http_response_code(404);
        echo "# Error: Interface not found\n";
        exit;
    }
    
    // Extract information for script generation
    $peer_name = $peer['name'] ?? 'Unnamed';
    $peer_public_key = $peer['public_key'] ?? '';
    $peer_allowed_ips = $peer['allowed_ips'] ?? '';
    
    // Extract peer IP without /32
    $peer_ip = extract_peer_ip($peer_allowed_ips);
    if ($peer_ip === 'N/A') {
        $peer_ip = '192.168.1.2'; // Fallback
    }
    
    // Interface information
    $interface_address = $interface_info['address'] ?? '192.168.1.1/24';
    $interface_port = $interface_info['port'] ?? '51820';
    $interface_public_key = $interface_info['public_key'] ?? '';
    
    // Parse interface address to get IP and network
    list($interface_ip, $cidr) = explode('/', $interface_address);
    $network_mask = (0xFFFFFFFF << (32 - intval($cidr))) & 0xFFFFFFFF;
    $network = long2ip(ip2long($interface_ip) & $network_mask);
    $network_with_cidr = $network . '/' . $cidr;
    
    // Get server endpoint from config or use placeholder
    $server_endpoint = get_server_endpoint(); // Function to get server's public endpoint
    
    // Clean interface name for MikroTik (remove special characters)
    $mikrotik_interface_name = 'wg_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $interface_name);
    
    // Set filename for download
    $filename = "mikrotik_setup_{$peer_name}_" . date('Y-m-d_H-i-s') . ".rsc";
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Generate MikroTik RouterOS script
    echo generate_mikrotik_script(
        $mikrotik_interface_name,
        $peer_ip . '/' . $cidr,
        $network,
        $server_endpoint,
        $interface_port,
        $interface_public_key,
        $peer_name
    );
    
} catch (Exception $e) {
    http_response_code(500);
    echo "# Error generating MikroTik script: " . $e->getMessage() . "\n";
}

/**
 * Generate MikroTik RouterOS script for WireGuard setup
 */
function generate_mikrotik_script($interface_name, $local_ip, $network, $endpoint, $port, $server_public_key, $peer_name) {
    $timestamp = date('Y-m-d H:i:s');
    
    return <<<SCRIPT
# MikroTik RouterOS WireGuard Setup Script
# Generated on: {$timestamp}
# Interface: {$interface_name}
# Peer: {$peer_name}
# Local IP: {$local_ip}
# Server Endpoint: {$endpoint}:{$port}

# Step 1: Create WireGuard interface (if it doesn't exist)
:if ([:len [/interface wireguard find where name="{$interface_name}"]] = 0) do={
    /interface wireguard add mtu=1420 name="{$interface_name}"
    :put "Created WireGuard interface: {$interface_name}"
} else={
    :put "WireGuard interface {$interface_name} already exists"
}

# Step 2: Add IP address to interface (if not already assigned)
:if ([:len [/ip address find where address~"{$local_ip}"]] = 0) do={
    /ip address add address="{$local_ip}" interface="{$interface_name}" network="{$network}"
    :put "Added IP address {$local_ip} to interface {$interface_name}"
} else={
    :put "IP address {$local_ip} already configured"
}

# Step 3: Add peer configuration (if not already exists)
:if ([:len [/interface wireguard peers find where endpoint-address="{$endpoint}"]] = 0) do={
    /interface wireguard peers add \\
        allowed-address="{$network}/{$local_ip}" \\
        endpoint-address="{$endpoint}" \\
        endpoint-port={$port} \\
        interface="{$interface_name}" \\
        persistent-keepalive=1m \\
        public-key="{$server_public_key}"
    :put "Added peer configuration for {$endpoint}:{$port}"
} else={
    :put "Peer configuration for {$endpoint} already exists"
}

# Step 4: Get and display the generated public key
:local wgPubKey [/interface wireguard get [find name="{$interface_name}"] value-name=public-key]

:put ""
:put "==================== WIREGUARD SETUP COMPLETED ===================="
:put ("Interface: " . "{$interface_name}")
:put ("Local IP: " . "{$local_ip}")
:put ("Peer Endpoint: " . "{$endpoint}:{$port}")
:put ("Peer Allowed Address: " . "{$network}/{$local_ip}")
:put ("Local Public Key: " . \$wgPubKey)
:put "===================================================================="
:put ""
:put "IMPORTANT: Copy the 'Local Public Key' shown above"
:put "You need to add this public key to your WireGuard server"
:put "as a peer with allowed IPs: {$local_ip}"
:put ""
:put "To add this peer to your server, run:"
:put ("sudo wg set {$interface_name} peer \" . \$wgPubKey . \" allowed-ips {$local_ip}")
:put ""

# Optional: Enable the interface if not already running
# Uncomment the next line if you want to automatically start the interface
# /interface wireguard enable [find name="{$interface_name}"]

SCRIPT;
}

/**
 * Get server endpoint (public IP or domain)
 */
function get_server_endpoint() {
    // Try to get from config file first
    if (file_exists(__DIR__ . '/../../config.php')) {
        require_once __DIR__ . '/../../config.php';
        if (defined('SERVER_ENDPOINT') && !empty(constant('SERVER_ENDPOINT'))) {
            return constant('SERVER_ENDPOINT');
        }
    }
    
    // Try to get public IP
    $public_ip = get_public_ip();
    if ($public_ip) {
        return $public_ip;
    }
    
    // Fallback to placeholder
    return 'YOUR_SERVER_IP_OR_DOMAIN';
}

/**
 * Get public IP address
 */
function get_public_ip() {
    $services = [
        'https://api.ipify.org',
        'https://ifconfig.me/ip',
        'https://icanhazip.com',
        'https://ipecho.net/plain'
    ];
    
    foreach ($services as $service) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'WireGuard Admin Script Generator'
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
?>