<?php
// Test the debug script with proper parameters
$_GET['peer_id'] = '1';
$_GET['interface'] = 'wg0';

echo "=== Testing Debug MikroTik Script ===\n\n";

ob_start();
include __DIR__ . '/app/backend/debug_mikrotik_script.php';
$output = ob_get_clean();

echo "Output:\n";
echo $output . "\n";
?>