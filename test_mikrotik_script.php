<?php
// Demo MikroTik Script Generator Test
require_once __DIR__ . '/includes/functions.php';

// Demo data - same as in the test.md file
$demo_interface = 'wg_trilink_cloudtik_wg';
$demo_local_ip = '192.168.14.3/24';
$demo_network = '192.168.14.0';
$demo_endpoint = 'secure.cloudtik.net';
$demo_port = '61670';
$demo_server_public_key = '4eghJTW/nJSy4W8HONve2fQihX/07M+ZXdlLWiwM2Xw=';
$demo_peer_name = 'Demo Client';

echo "Content-Type: text/plain\n\n";
echo "Demo MikroTik RouterOS Script Generated:\n";
echo "=====================================\n\n";

// Generate the script
$script = generate_demo_mikrotik_script(
    $demo_interface,
    $demo_local_ip,
    $demo_network,
    $demo_endpoint,
    $demo_port,
    $demo_server_public_key,
    $demo_peer_name
);

echo $script;

function generate_demo_mikrotik_script($interface_name, $local_ip, $network, $endpoint, $port, $server_public_key, $peer_name) {
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
        allowed-address="{$network}/24" \\
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
:put ("Peer Allowed Address: " . "{$network}/24")
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
?>