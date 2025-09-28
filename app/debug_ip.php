<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "<h2>Debug: Checking IP Generation</h2>\n";

try {
    $db = get_db();
    echo "✓ Database connection successful<br>\n";
    
    // Check if interfaces table exists
    try {
        $stmt = $db->query('SELECT COUNT(*) FROM interfaces');
        $count = $stmt->fetchColumn();
        echo "✓ Interfaces table exists, {$count} interfaces found<br>\n";
        
        // List all interfaces
        $stmt = $db->query('SELECT * FROM interfaces');
        $interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Available Interfaces:</h3>\n";
        foreach ($interfaces as $iface) {
            echo "- ID: {$iface['id']}, Name: {$iface['name']}, Address: {$iface['address']}, Status: {$iface['status']}<br>\n";
        }
    } catch (Exception $e) {
        echo "⚠ Interfaces table issue: " . $e->getMessage() . "<br>\n";
    }
    
    // Check if peers table exists
    try {
        $stmt = $db->query('SELECT COUNT(*) FROM peers');
        $count = $stmt->fetchColumn();
        echo "✓ Peers table exists, {$count} peers found<br>\n";
    } catch (Exception $e) {
        echo "⚠ Peers table issue: " . $e->getMessage() . "<br>\n";
        echo "⚠ This might be why IP generation is failing<br>\n";
        
        // Try to check what columns exist in peers table
        try {
            $stmt = $db->query('DESCRIBE peers');
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<h3>Peers Table Structure:</h3>\n";
            foreach ($columns as $col) {
                echo "- {$col['Field']} ({$col['Type']})<br>\n";
            }
        } catch (Exception $e2) {
            echo "⚠ Cannot describe peers table: " . $e2->getMessage() . "<br>\n";
        }
    }
    
    // Test IP generation for each interface
    $available_interfaces = get_available_interfaces();
    echo "<h3>Testing IP Generation:</h3>\n";
    foreach ($available_interfaces as $iface) {
        include_once 'wg-peers.php';
        // We need to define the function here since we're including the file
        $next_ip = getNextAvailableIP($iface);
        echo "Interface {$iface}: " . ($next_ip ? $next_ip : 'FAILED') . "<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>\n";
}
?>