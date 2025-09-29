<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/../includes/functions.php';

echo "<h2>Debug: Testing Peer Filtering by Interface</h2>\n";

try {
    $db = new \WireGuardAdmin\Database();
    echo "✓ Database connection successful<br>\n";
    
    // Ensure tables exist
    ensure_peers_table();
    ensure_interfaces_table();
    echo "✓ Tables ensured<br>\n";
    
    // Check available interfaces
    $available_interfaces = get_available_interfaces();
    echo "<h3>Available Interfaces:</h3>\n";
    foreach ($available_interfaces as $iface) {
        echo "- {$iface}<br>\n";
        
        // Test WireGuard class with this interface
        try {
            $wg_instance = new \WireGuardAdmin\WireGuard($db, $iface);
            echo "  ✓ WireGuard instance created<br>\n";
            echo "  - Interface Name: " . $wg_instance->getInterfaceName() . "<br>\n";
            echo "  - Config Path: " . $wg_instance->getConfigPath() . "<br>\n";
            
            // Get peers for this interface
            $peers = $wg_instance->getPeers();
            echo "  - Peers count: " . count($peers) . "<br>\n";
            
            foreach ($peers as $peer) {
                echo "    * {$peer['name']} (IP: {$peer['allowed_ips']}, Interface: {$peer['iface_id']})<br>\n";
            }
            
        } catch (Exception $e) {
            echo "  ❌ Error with interface {$iface}: " . $e->getMessage() . "<br>\n";
        }
        echo "<br>\n";
    }
    
    // Check total peers in database
    $db_connection = get_db();
    $total_peers = $db_connection->query('SELECT COUNT(*) FROM wg_peers')->fetchColumn();
    echo "<h3>Total peers in database: {$total_peers}</h3>\n";
    
    if ($total_peers > 0) {
        $all_peers = $db_connection->query('SELECT * FROM wg_peers ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
        echo "<h4>All peers:</h4>\n";
        foreach ($all_peers as $peer) {
            echo "- ID: {$peer['id']}, Name: {$peer['name']}, Interface: {$peer['iface_id']}, IP: {$peer['allowed_ips']}, Status: {$peer['status']}<br>\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>\n";
}
?>