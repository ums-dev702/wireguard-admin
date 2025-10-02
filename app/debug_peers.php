<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/../includes/functions.php';

echo "<h2>Debug: Testing Interface-Specific Peer Filtering</h2>\n";

try {
    // Ensure tables exist
    ensure_peers_table();
    
    $db = new \WireGuardAdmin\Database();
    echo "✓ Database connection successful<br>\n";
    
    // Get available interfaces
    $available_interfaces = get_available_interfaces();
    echo "Available interfaces: " . implode(', ', $available_interfaces) . "<br>\n";
    
    foreach ($available_interfaces as $interface) {
        echo "<h3>Testing interface: {$interface}</h3>\n";
        
        // Create WireGuard instance for this interface
        $wg_instance = new \WireGuardAdmin\WireGuard($db, $interface);
        echo "✓ WireGuard instance created for {$interface}<br>\n";
        echo "Interface name in WireGuard class: " . $wg_instance->getInterfaceName() . "<br>\n";
        
        // Get peers for this interface
        $peers = $wg_instance->getPeers(false); // Get all peers, not just active
        echo "Found " . count($peers) . " peers for interface {$interface}<br>\n";
        
        if (!empty($peers)) {
            echo "<ul>\n";
            foreach ($peers as $peer) {
                echo "<li>Peer: {$peer['name']} - {$peer['allowed_ips']} (Interface: {$peer['iface_id']}, Status: {$peer['status']})</li>\n";
            }
            echo "</ul>\n";
        }
        echo "<hr>\n";
    }
    
    // Show all peers in the database
    echo "<h3>All peers in wg_peers table:</h3>\n";
    $all_peers = $db->select("SELECT * FROM wg_peers ORDER BY created_at DESC");
    echo "Total peers in database: " . count($all_peers) . "<br>\n";
    
    if (!empty($all_peers)) {
        echo "<ul>\n";
        foreach ($all_peers as $peer) {
            echo "<li>ID: {$peer['id']} - Name: {$peer['name']} - Interface: {$peer['iface_id']} - Status: {$peer['status']} - IPs: {$peer['allowed_ips']}</li>\n";
        }
        echo "</ul>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>\n";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>