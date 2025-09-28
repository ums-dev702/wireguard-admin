<?php
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/autoloader.php';

try {
    $db = new \WireGuardAdmin\Database();
    
    echo "<h2>Testing WireGuard Class with Auto Interface Detection</h2>\n";
    
    // Test 1: Create instance without parameters (should auto-detect first interface)
    echo "<h3>Test 1: Auto-detection (no parameters)</h3>\n";
    $wg1 = new \WireGuardAdmin\WireGuard($db);
    echo "Interface Name: " . $wg1->getInterfaceName() . "\n";
    echo "Config Path: " . $wg1->getConfigPath() . "\n";
    echo "Status: " . $wg1->getStatus() . "\n\n";
    
    // Test 2: Create instance with specific interface
    echo "<h3>Test 2: Specific interface (manual override)</h3>\n";
    $wg2 = new \WireGuardAdmin\WireGuard($db, 'wg_custom', '/etc/wireguard/wg_custom.conf');
    echo "Interface Name: " . $wg2->getInterfaceName() . "\n";
    echo "Config Path: " . $wg2->getConfigPath() . "\n";
    echo "Status: " . $wg2->getStatus() . "\n\n";
    
    // Test 3: Refresh interface to get latest from DB
    echo "<h3>Test 3: Refresh interface</h3>\n";
    $refreshed = $wg2->refreshInterface();
    echo "Refresh successful: " . ($refreshed ? 'Yes' : 'No') . "\n";
    if ($refreshed) {
        echo "New Interface Name: " . $wg2->getInterfaceName() . "\n";
        echo "New Config Path: " . $wg2->getConfigPath() . "\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>