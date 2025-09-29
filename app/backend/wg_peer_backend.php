<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../autoloader.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Ensure user is authenticated
if (!isset($_SESSION['user_authenticated']) || $_SESSION['user_authenticated'] !== true) {
    header('Location: /login.php');
    exit;
}

// Get current user
$db = new \WireGuardAdmin\Database();
$auth = new \WireGuardAdmin\Auth($db, SESSION_TIMEOUT);
$currentUser = $auth->getCurrentUser();

if (isset($_POST['create_peer'])) {
    $peer_name   = trim($_POST['peer_name'] ?? '');
    $interface   = trim($_POST['interface'] ?? '');
    $allowed_ips = trim($_POST['allowed_ips'] ?? '');
    $user_id = $currentUser['id'] ?? null;

    if (empty($interface)) {
        header('Location: ../../wg-peers?error=' . urlencode('Interface is required'));
        exit;
    }
    
    if (empty($peer_name)) {
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=' . urlencode('Peer name is required'));
        exit;
    }

    if (empty($allowed_ips)) {
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=' . urlencode('Allowed IPs is required'));
        exit;
    }

    try {
        // Ensure tables exist
        ensure_peers_table();
        
        // Initialize WireGuard instance
        $wg_instance = new \WireGuardAdmin\WireGuard($db, $interface);
        
        // Create the peer
        $peer_data = $wg_instance->createPeer($peer_name, $allowed_ips, '8.8.8.8,1.1.1.1');
        
        $success_message = "Peer '{$peer_name}' created successfully on interface {$interface}!";

        // Log activity
        if ($auth && $user_id) {
            $auth->logActivity(
                $user_id,
                'CREATE_PEER',
                "Created WireGuard peer: {$peer_name} ({$allowed_ips}) on interface {$interface}",
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
        }
        
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&success=' . urlencode($success_message));
        exit;
        
    } catch (Exception $e) {
        $error_message = "Failed to create peer: " . $e->getMessage();
        error_log("Peer creation error: " . $e->getMessage());
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=' . urlencode($error_message));
        exit;
    }
}

if (isset($_POST['delete_peer'])) {
    $peer_id = intval($_POST['peer_id'] ?? 0);
    $interface = trim($_POST['interface'] ?? '');
    $user_id = $currentUser['id'] ?? null;

    if (empty($interface)) {
        header('Location: ../../wg-peers?error=' . urlencode('Interface is required'));
        exit;
    }
    
    if (empty($peer_id)) {
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=' . urlencode('Peer ID is required'));
        exit;
    }

    try {
        // Initialize WireGuard instance
        $wg_instance = new \WireGuardAdmin\WireGuard($db, $interface);
        
        // Get peer info before deletion
        $peer = $wg_instance->getPeer($peer_id);
        if (!$peer) {
            header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=' . urlencode('Peer not found'));
            exit;
        }

        // Delete the peer
        $wg_instance->deletePeer($peer_id);
        $success_message = "Peer '{$peer['name']}' removed successfully from interface {$interface}!";

        // Log activity
        if ($auth && $user_id) {
            $auth->logActivity(
                $user_id,
                'DELETE_PEER',
                "Deleted WireGuard peer: {$peer['name']} from interface {$interface}",
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
        }
        
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&success=' . urlencode($success_message));
        exit;
        
    } catch (Exception $e) {
        $error_message = "Failed to remove peer: " . $e->getMessage();
        error_log("Peer deletion error: " . $e->getMessage());
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=' . urlencode($error_message));
        exit;
    }
}

// Remove the old unused functions and update handlers
?>
