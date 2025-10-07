<?php
/**
 * Test script for the extract_peer_ip function
 */

require_once __DIR__ . '/includes/functions.php';

echo "=== Peer IP Extraction Test ===\n\n";

// Test various IP formats
$test_ips = [
    '10.0.0.2/32',
    '192.168.1.100/32',
    '10.7.0.4/32, 192.168.1.0/24',
    '172.16.0.50/32',
    '',
    'invalid-ip',
    '10.0.0.1',
    '10.0.0.2/24',
    null
];

foreach ($test_ips as $test_ip) {
    $result = extract_peer_ip($test_ip ?? '');
    echo "Input: " . var_export($test_ip, true) . "\n";
    echo "Output: " . $result . "\n";
    echo "---\n";
}

echo "\n=== Test Complete ===\n";
?>