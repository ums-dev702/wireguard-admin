<?php
/**
 * WireGuard Peer Addition Demo
 * This page demonstrates the automatic peer addition functionality
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';
require_once __DIR__ . '/includes/functions.php';

// Example usage of the new functionality
echo "=== WireGuard Peer Addition Demo ===\n\n";

// Example parameters (these would normally come from user input)
$example_interface = "acs";  // or "wg0", etc.
$example_public_key = "uKJVHa0NZoI9q3Jp6IHB4QrldrYftDMOW4db1+s5f2k=";
$example_allowed_ips = "10.7.0.5/32";

echo "Example command that would be executed:\n";
echo "sudo wg set wg_{$example_interface} peer {$example_public_key} allowed-ips {$example_allowed_ips}\n\n";

// Simulate the function call (commented out to avoid actually executing)
echo "Simulating function call:\n";
echo "add_peer_to_wireguard_interface('{$example_interface}', '{$example_public_key}', '{$example_allowed_ips}')\n\n";

/*
// Uncomment this section to actually test the function
$result = add_peer_to_wireguard_interface($example_interface, $example_public_key, $example_allowed_ips);

echo "Result:\n";
print_r($result);

if ($result['success']) {
    echo "\n✅ Peer would be successfully added to WireGuard interface!\n";
    echo "Command executed: " . $result['command'] . "\n";
} else {
    echo "\n❌ Failed to add peer:\n";
    echo "Error: " . $result['message'] . "\n";
}
*/

echo "\n=== How it works ===\n";
echo "1. User provides a public key in the web interface\n";
echo "2. System validates the key format\n";
echo "3. System executes: sudo wg set wg_INTERFACE peer PUBLIC_KEY allowed-ips ALLOWED_IPS\n";
echo "4. System saves the configuration: sudo wg-quick save wg_INTERFACE\n";
echo "5. System sends Telegram notification (if configured)\n";
echo "6. Peer is now active in the WireGuard interface\n\n";

echo "=== Web Interface Integration ===\n";
echo "- When user clicks 'Edit Public Key' and pastes a key\n";
echo "- The peer is automatically added to the WireGuard interface\n";
echo "- User sees success message with command that was executed\n";
echo "- Peer status changes from 'unconfigured' to 'active'\n\n";

echo "=== Example Telegram Notification ===\n";
echo "✅ WireGuard Peer Added via Function\n";
echo "===================================\n";
echo "Interface: wg_{$example_interface}\n";
echo "Public Key: " . substr($example_public_key, 0, 20) . "...\n";
echo "Allowed IPs: {$example_allowed_ips}\n";
echo "Command: sudo wg set wg_{$example_interface} peer {$example_public_key} allowed-ips {$example_allowed_ips}\n";
echo "Status: ✅ Active\n";
echo "===================================\n";

echo "\n=== Test Complete ===\n";
?>