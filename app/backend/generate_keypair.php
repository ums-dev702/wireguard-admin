<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../autoloader.php';
require_once __DIR__ . '/../../includes/auth.php';

// Check if user is authenticated
if (!isset($_SESSION['user_authenticated']) || $_SESSION['user_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Generate WireGuard key pair
    $privateKey = trim(shell_exec('wg genkey'));
    $publicKey = trim(shell_exec("echo '{$privateKey}' | wg pubkey"));
    
    if (empty($privateKey) || empty($publicKey)) {
        throw new Exception('Failed to generate key pair');
    }
    
    echo json_encode([
        'success' => true,
        'private_key' => $privateKey,
        'public_key' => $publicKey
    ]);
    
} catch (Exception $e) {
    error_log("Key pair generation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate key pair: ' . $e->getMessage()
    ]);
}
?>