<?php
// Debug version of MikroTik script generation (bypasses auth for testing)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Set content type for download
header('Content-Type: text/plain');

// Get parameters
$peer_id = $_GET['peer_id'] ?? '';
$interface = $_GET['interface'] ?? '';

// Debug logging
error_log("DEBUG MikroTik script request - peer_id: $peer_id, interface: $interface");

if (empty($peer_id)) {
    http_response_code(400);
    echo "# Error: Missing peer_id parameter\n";
    echo "# Please provide a valid peer ID\n";
    exit;
}

if (empty($interface)) {
    http_response_code(400);
    echo "# Error: Missing interface parameter\n";
    echo "# Please select an interface first\n";
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
        echo "# Peer ID '{$peer_id}' does not exist in database\n";
        
        // Show available peers
        $stmt = $db->query('SELECT id, name FROM wg_peers LIMIT 5');
        $peers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "# Available peers:\n";
        foreach ($peers as $p) {
            echo "#   ID: {$p['id']}, Name: {$p['name']}\n";
        }
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
        echo "# Interface '{$interface_name}' does not exist in database\n";
        
        // Show available interfaces
        $stmt = $db->query('SELECT name FROM interfaces LIMIT 5');
        $interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "# Available interfaces:\n";
        foreach ($interfaces as $iface) {
            echo "#   {$iface['name']}\n";
        }
        exit;
    }
    
    // Generate script (simplified version)
    echo "# MikroTik RouterOS WireGuard Setup Script (DEBUG VERSION)\n";
    echo "# Generated on: " . date('Y-m-d H:i:s') . "\n";
    echo "# Peer: {$peer['name']}\n";
    echo "# Interface: {$interface_name}\n\n";
    
    echo "# This is a debug version - script generation successful!\n";
    echo "# Peer ID: {$peer_id}\n";
    echo "# Peer Name: {$peer['name']}\n";
    echo "# Peer Public Key: {$peer['public_key']}\n";
    echo "# Peer Allowed IPs: {$peer['allowed_ips']}\n";
    echo "# Interface: {$interface_info['name']}\n";
    echo "# Interface Address: {$interface_info['address']}\n";
    echo "# Interface Port: {$interface_info['port']}\n";
    
} catch (Exception $e) {
    http_response_code(500);
    echo "# Error generating MikroTik script: " . $e->getMessage() . "\n";
    echo "# File: " . $e->getFile() . "\n";
    echo "# Line: " . $e->getLine() . "\n";
    error_log("DEBUG MikroTik script generation error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>