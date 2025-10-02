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

$db = new \WireGuardAdmin\Database();
$auth = new \WireGuardAdmin\Auth($db, SESSION_TIMEOUT);
$currentUser = $auth->getCurrentUser();

if (isset($_POST['create_peer'])) {
    $peer_name   = $_POST['peer_name'] ?? '';
    $interface   = $_POST['interface'] ?? '';
    $allowed_ips = $_POST['allowed_ips'] ?? '';
    $user_id = $currentUser['id'] ?? null;

    // Remove wg_ prefix to get the actual interface name
    $actual_interface = preg_replace('/^wg_/', '', $interface);

    // Get interface details
    $interface_details = get_interface_details($actual_interface);
    $iface_id = $interface_details['iface_id'] ?? '';

    if (!$interface_details) {
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=Invalid interface');
        exit;
    }

    try {
        // Ensure tables exist
        ensure_peers_table();
        
        // Initialize WireGuard instance with the full interface name (with wg_ prefix)
        $wg_instance = new \WireGuardAdmin\WireGuard($db, $interface);
        
        // Create the peer using WireGuard class
        $peer_data = $wg_instance->createPeer($peer_name, $allowed_ips, '8.8.8.8,1.1.1.1');
        
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

        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&success=Peer created successfully');
        exit;
    } catch (Exception $e) {
        error_log("Error creating peer: " . $e->getMessage());
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=Failed to create peer: ' . $e->getMessage());
        exit;
    }
}

if (isset($_POST['delete_peer'])) {
    $peer_id = $_POST['peer_id'] ?? '';
    $interface = $_POST['interface'] ?? '';
    $user_id = $currentUser['id'] ?? null;
    
    try {
        // Initialize WireGuard instance
        $wg_instance = new \WireGuardAdmin\WireGuard($db, $interface);
        
        // Get peer info before deletion for logging
        $peer = $wg_instance->getPeer($peer_id);
        
        if (!$peer) {
            header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=Peer not found');
            exit;
        }
        
        // Delete the peer using WireGuard class
        $wg_instance->deletePeer($peer_id);
        
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
        
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&success=Peer deleted successfully');
        exit;
    } catch (Exception $e) {
        error_log("Error deleting peer: " . $e->getMessage());
        header('Location: ../../wg-peers?interface=' . urlencode($interface) . '&error=Failed to delete peer: ' . $e->getMessage());
        exit;
    }
}

if (isset($_POST['update_peer'])) {
  $peer_id = $_POST['peer_id'] ?? '';
  $interface = $_POST['interface'] ?? '';
  $allowed_ips = $_POST['allowed_ips'] ?? '';
  $endpoint = $_POST['endpoint'] ?? '';
  try {
    $db = get_db();
    $stmt = $db->prepare('UPDATE wg_peers SET allowed_ips = ?, endpoint = ? WHERE peer_id = ?');
    $stmt->execute([$allowed_ips, $endpoint, $peer_id]);
    header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&success=Peer updated successfully');
    exit;
  } catch (Exception $e) {
    error_log("Error updating peer: " . $e->getMessage());
    header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Failed to update peer');
    exit;
  }
}


function get_interface_details($interface)
{
  try {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM interfaces WHERE name = ?');
    $stmt->execute([$interface]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    error_log("Error fetching interface details: " . $e->getMessage());
    return null;
  }
}
