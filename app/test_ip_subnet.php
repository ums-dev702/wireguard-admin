<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Test IP generation with actual interface data
echo "<h2>Testing IP Generation from Interface Subnet</h2>\n";

try {
    $db = get_db();
    
    // Get interfaces
    $interfaces = get_available_interfaces();
    
    if (empty($interfaces)) {
        echo "❌ No interfaces found. Please create an interface first.\n";
        echo "<br><a href='../create_interface'>Create Interface</a>\n";
        exit;
    }
    
    foreach ($interfaces as $interface) {
        echo "<h3>Testing Interface: {$interface}</h3>\n";
        
        // Get interface data
        $stmt = $db->prepare('SELECT * FROM interfaces WHERE name = ?');
        $stmt->execute([$interface]);
        $iface_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($iface_data) {
            echo "Interface Address: {$iface_data['address']}<br>\n";
            
            // Parse subnet
            [$subnet_ip, $cidr] = explode('/', $iface_data['address']);
            $ip_int = ip2long($subnet_ip);
            $host_bits = 32 - intval($cidr);
            $network_mask = ~((1 << $host_bits) - 1);
            $network_int = $ip_int & $network_mask;
            $first_usable = $network_int + 1;
            $last_usable = $network_int + (1 << $host_bits) - 2;
            
            echo "Subnet IP: {$subnet_ip}<br>\n";
            echo "CIDR: /{$cidr}<br>\n";
            echo "Network: " . long2ip($network_int) . "<br>\n";
            echo "First Usable: " . long2ip($first_usable) . "<br>\n";
            echo "Last Usable: " . long2ip($last_usable) . "<br>\n";
            echo "Total Usable IPs: " . ($last_usable - $first_usable + 1) . "<br>\n";
        }
        
        // Include the IP generation function
        include_once 'wg-peers.php';
        
        // Test generating multiple IPs
        echo "<h4>Generated IPs:</h4>\n";
        for ($i = 1; $i <= 5; $i++) {
            $next_ip = getNextAvailableIP($interface);
            echo "{$i}. " . ($next_ip ? $next_ip : 'No IP available') . "<br>\n";
            
            if (!$next_ip) break;
        }
        
        echo "<hr>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>