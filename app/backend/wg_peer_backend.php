<?php
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../autoloader.php';
include_once __DIR__ . '/../../includes/functions.php';
$db = new \WireGuardAdmin\Database();


if (isset($_POST['create_peer'])) {
  $peer_name = $_POST['peer_name'] ?? '';
  $interface = $_POST['interface'] ?? '';
  $allowed_ips = $_POST['allowed_ips'] ?? '';
  //add peer to the db
  //generate a peer id prefix PRE-
  $peer_name = 'PRE' . rand(1000, 9999);
  try {
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO wg_peers (name, interface, allowed_ips,status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$peer_name, $interface, $allowed_ips, 'unconfigured']);
    header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&success=Peer created successfully');
    exit;
  } catch (Exception $e) {
    error_log("Error creating peer: " . $e->getMessage());
    header('Location: ../../wg_peers?interface=' . urlencode($interface) . '&error=Failed to create peer');
    exit;
  }
}






