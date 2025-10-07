<?php
/**
 * Test script for port validation functions
 * Run this to verify that port checking works correctly
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== Port Validation Test ===\n\n";

// Test a few common ports that are likely to be in use
$test_ports = [22, 80, 443, 51820, 20000, 65000];

foreach ($test_ports as $port) {
    echo "Testing port $port:\n";
    
    // Test individual functions
    echo "  Socket binding check: " . (is_port_completely_free($port) ? "FREE" : "IN USE") . "\n";
    echo "  UFW check: " . (is_port_in_ufw($port) ? "IN UFW" : "NOT IN UFW") . "\n";
    echo "  Port forwarding check: " . (is_port_in_port_forwarding($port) ? "IN PORT FORWARDING" : "NOT IN PORT FORWARDING") . "\n";
    
    // Test comprehensive validation
    $validation = validate_wireguard_port($port);
    echo "  Overall validation: " . ($validation['valid'] ? "VALID" : "INVALID") . "\n";
    echo "  Message: " . $validation['message'] . "\n";
    
    echo "\n";
}

// Test find_free_udp_port function
echo "Finding a free UDP port in range 20000-20010:\n";
$free_port = find_free_udp_port(20000, 20010);
if ($free_port) {
    echo "Found free port: $free_port\n";
    
    // Validate the found port
    $validation = validate_wireguard_port($free_port);
    echo "Validation: " . ($validation['valid'] ? "VALID" : "INVALID") . "\n";
    echo "Message: " . $validation['message'] . "\n";
} else {
    echo "No free port found in range\n";
}

echo "\n=== Test Complete ===\n";
?>