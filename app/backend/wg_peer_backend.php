<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../autoloader.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';


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
        header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Invalid interface');
        exit;
    }

    try {
        // Ensure tables exist
        ensure_peers_table();

        // Initialize WireGuard instance with the full interface name (with wg_ prefix)
        $wg_instance = new \WireGuardAdmin\WireGuard($db, $interface);
        
        // Create the peer using WireGuard class
        $peer_data = $wg_instance->createPeer($peer_name, $iface_id, $allowed_ips);
        
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

        header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&success=Peer created successfully');
        exit;
    } catch (Exception $e) {
        error_log("Error creating peer: " . $e->getMessage());
        header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Failed to create peer: ' . $e->getMessage());
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
            header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Peer not found');
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
        
        header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&success=Peer deleted successfully');
        exit;
    } catch (Exception $e) {
        error_log("Error deleting peer: " . $e->getMessage());
        header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Failed to delete peer: ' . $e->getMessage());
        exit;
    }
}

// Handle public key update
if (isset($_POST['edit_public_key'])) {
    $peer_id = $_POST['peer_id'] ?? '';
    $interface = $_POST['interface'] ?? '';
    $public_key = trim($_POST['public_key'] ?? '');
    $user_id = $currentUser['id'] ?? null;
    
    if (empty($peer_id) || empty($public_key)) {
        header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Peer ID and public key are required');
        exit;
    }
    
    try {
        // Initialize WireGuard instance
        $wg_instance = new \WireGuardAdmin\WireGuard($db, $interface);
        
        // Get peer info for logging
        $peer = $wg_instance->getPeer($peer_id);
        
        if (!$peer) {
            header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Peer not found');
            exit;
        }
        
        // Update the public key directly in the database
        $db->update('wg_peers', [
            'public_key' => $public_key,
            'status' => 'active'  // Mark as active since it now has a key
        ], 'id = :peer_id', ['peer_id' => $peer_id]);
        
        // Add peer to WireGuard interface using wg set command
        $actual_interface = preg_replace('/^wg_/', '', $interface);
        $allowed_ips = $peer['allowed_ips'];
        
        // Execute the wg set command to add the peer to the interface
        $wg_command = "sudo wg set wg_{$actual_interface} peer {$public_key} allowed-ips {$allowed_ips}";
        $wg_output = shell_exec($wg_command . ' 2>&1');
        
        // Check if command was successful
        if ($wg_output === null || empty(trim($wg_output))) {
            // Command was successful (no output usually means success)
            $success_message = "Peer added to WireGuard interface successfully";
            
            // Save the configuration to make it persistent
            $save_command = "sudo wg-quick save wg_{$actual_interface}";
            shell_exec($save_command . ' 2>&1');
            
            // Send Telegram notification if configured
            if (function_exists('sendToTelegram')) {
                $telegram_msg = "✅ WireGuard Peer Added\n";
                $telegram_msg .= "========================\n";
                $telegram_msg .= "Interface: wg_{$actual_interface}\n";
                $telegram_msg .= "Peer Name: {$peer['name']}\n";
                $telegram_msg .= "Public Key: " . substr($public_key, 0, 20) . "...\n";
                $telegram_msg .= "Allowed IPs: {$allowed_ips}\n";
                $telegram_msg .= "Command: {$wg_command}\n";
                $telegram_msg .= "========================\n";
                sendToTelegram($telegram_msg);
            }
        } else {
            // Command failed, log the error but still mark as updated in DB
            error_log("WireGuard command failed: " . $wg_output);
            $success_message = "Public key updated in database, but failed to add to WireGuard interface: " . $wg_output;
        }
        
        // Log activity
        if ($auth && $user_id) {
            $auth->logActivity(
                $user_id,
                'UPDATE_PEER_KEY',
                "Updated public key for WireGuard peer: {$peer['name']} on interface {$interface}. Command: {$wg_command}",
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
        }
        
        header('Location: /app/wg-peers.php?interface=' . urlencode($interface) . '&success=' . urlencode($success_message));
        exit;
    } catch (Exception $e) {
        error_log("Error updating public key: " . $e->getMessage());
        header('Location: /app/wg-peers.php?interface=' . urlencode($interface) . '&error=Failed to update public key: ' . $e->getMessage());
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


