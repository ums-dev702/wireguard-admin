<?php
/**
 * Test page for IP generation functions
 */
require_once '../config.php';
require_once '../includes/functions.php';

echo "<h1>WireGuard IP Generation Test</h1>";

// Test getting available interfaces
echo "<h2>Available Interfaces:</h2>";
$interfaces = get_available_interfaces();
echo "<pre>";
print_r($interfaces);
echo "</pre>";

// Test IP generation for each interface
echo "<h2>Next Available IPs:</h2>";
foreach ($interfaces as $interface => $info) {
    echo "<h3>Interface: {$interface}</h3>";
    
    // Get interface config
    $config = getInterfaceConfig($interface);
    if ($config && isset($config['Address'])) {
        echo "Interface Address: " . htmlspecialchars($config['Address']) . "<br>";
        
        // Generate next IP
        $nextIP = getNextAvailableIP($interface);
        echo "Next Available IP: " . htmlspecialchars($nextIP) . "<br>";
        
        // Test if IP is in use
        $isInUse = isIPInUse($nextIP);
        echo "Is IP in use: " . ($isInUse ? 'Yes' : 'No') . "<br>";
        
        echo "<br>";
    } else {
        echo "Could not read interface config<br><br>";
    }
}

// Test database connection
echo "<h2>Database Test:</h2>";
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    echo "Database connection: OK<br>";
    
    // Check if interfaces table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'interfaces'");
    if ($stmt->rowCount() > 0) {
        echo "Interfaces table: EXISTS<br>";
        
        // Show interface records
        $stmt = $pdo->query("SELECT * FROM interfaces");
        $interfaces_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Interface records in DB:<br>";
        echo "<pre>";
        print_r($interfaces_db);
        echo "</pre>";
    } else {
        echo "Interfaces table: MISSING<br>";
    }
    
    // Check if peers table exists  
    $stmt = $pdo->query("SHOW TABLES LIKE 'peers'");
    if ($stmt->rowCount() > 0) {
        echo "Peers table: EXISTS<br>";
        
        // Show peer records
        $stmt = $pdo->query("SELECT id, name, allowed_ips, interface_name FROM peers LIMIT 5");
        $peers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample peer records:<br>";
        echo "<pre>";
        print_r($peers);
        echo "</pre>";
    } else {
        echo "Peers table: MISSING<br>";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f5f5f5;
}
h1, h2, h3 {
    color: #333;
}
pre {
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow-x: auto;
}
</style>