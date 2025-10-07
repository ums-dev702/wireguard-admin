<?php
// Simple test to check MikroTik script generation

// Start session for authentication
session_start();

echo "=== MikroTik Script Generation Test ===\n\n";

// Test direct access to the backend script
$test_peer_id = "1"; // Assuming peer ID 1 exists
$test_interface = "wg0"; // Assuming interface wg0 exists

echo "Testing with:\n";
echo "- Peer ID: {$test_peer_id}\n";
echo "- Interface: {$test_interface}\n\n";

// Check if user is logged in (simulate login)
$_SESSION['authenticated'] = true;
$_SESSION['username'] = 'test_user';

// Include the backend script logic directly
try {
    // Set up the environment
    $_GET['peer_id'] = $test_peer_id;
    $_GET['interface'] = $test_interface;
    
    // Capture output
    ob_start();
    
    // Include the backend script
    include __DIR__ . '/app/backend/generate_mikrotik_script.php';
    
    $output = ob_get_clean();
    
    echo "✅ Backend script executed successfully\n";
    echo "Output length: " . strlen($output) . " characters\n";
    echo "First 200 characters:\n";
    echo substr($output, 0, 200) . "...\n\n";
    
    if (strpos($output, '# Error:') === 0) {
        echo "❌ Script returned an error:\n";
        echo $output . "\n";
    } else {
        echo "✅ Script generated successfully\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Debug Information ===\n";
echo "Session data: " . print_r($_SESSION, true) . "\n";
echo "GET parameters: " . print_r($_GET, true) . "\n";

echo "\n=== Recommendations ===\n";
echo "1. Check if peer ID 1 exists in wg_peers table\n";
echo "2. Check if interface 'wg0' exists in interfaces table\n";
echo "3. Verify database connection is working\n";
echo "4. Check authentication system\n";
echo "5. Look for PHP errors in error log\n";
?>