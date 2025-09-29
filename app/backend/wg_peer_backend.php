<?php
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../autoloader.php';
include_once __DIR__ . '/../../includes/functions.php';
$db = new \WireGuardAdmin\Database();


if (isset($_POST['create_peer'])) {
  $peer_name   = $_POST['peer_name'] ?? '';   // user-provided name
  $interface   = $_POST['interface'] ?? '';
  $allowed_ips = $_POST['allowed_ips'] ?? '';

  // get interface details
  $interface_details = get_interface_details($interface);
  $iface_id = $interface_details['iface_id'] ?? '';

  if (!$interface_details) {
    header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Invalid interface');
    exit;
  }

  // generate a peer_id with prefix
  $peer_id = 'PRE' . rand(1000, 9999);

  try {
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO wg_peers (peer_id, name, iface_id, allowed_ips, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$peer_id, $peer_name, $iface_id, $allowed_ips, 'unconfigured']);

    header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&success=Peer created successfully');
    exit;
  } catch (Exception $e) {
    error_log("Error creating peer: " . $e->getMessage());
    header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Failed to create peer');
    exit;
  }
}

if (isset($_POST['delete_peer'])) {
  $peer_id = $_POST['peer_id'] ?? '';
  $interface = $_POST['interface'] ?? '';
  try {
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM wg_peers WHERE peer_id = ?');
    $stmt->execute([$peer_id]);
    header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&success=Peer deleted successfully');
    exit;
  } catch (Exception $e) {
    error_log("Error deleting peer: " . $e->getMessage());
    header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Failed to delete peer');
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


function get_interface_details($interface_id)
{
  try {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM interfaces WHERE iface_id = ?');
    $stmt->execute([$interface_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    error_log("Error fetching interface details: " . $e->getMessage());
    return null;
  }
}
