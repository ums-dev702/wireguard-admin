<?php
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../autoloader.php';
include_once __DIR__ . '/../../includes/functions.php';

// Function to handle interface creation
function createWireGuardInterface($iface, $private_key, $address, $listen_port)
{
    global $error, $success;

    // Validation
    if (strlen($iface) > 8) {
        $error = "Interface name must not exceed 8 characters.";
        sendToTelegram("Error: " . $error);

        return false;
    }

    if (!$iface || !$private_key || !$address) {
        $error = "All required fields must be filled.";
        sendToTelegram("Error: " . $error);
        return false;
    }

    $conf_path = "/etc/wireguard/wg_$iface.conf";

    // Check if interface already exists
    if (file_exists($conf_path)) {
        $error = "Interface configuration already exists.";
        sendToTelegram("Error: " . $error);
        return false;
    }

    // Create configuration
    $conf = generateInterfaceConfig($private_key, $address, $listen_port);

    // Write configuration file
    if (file_put_contents($conf_path, $conf) === false) {
        $error = "Failed to write configuration file.";
        sendToTelegram("Error: " . $error);
        return false;
    }

    // Configure firewall
    if (!configureFirewall($listen_port)) {
        $error = "Failed to configure firewall rules.";
        sendToTelegram("Error: " . $error);
        // Clean up created file
        unlink($conf_path);
        return false;
    }

    // Start interface
    if (!startInterface($iface)) {
        $error = "Failed to start WireGuard interface.";
        sendToTelegram("Error: " . $error);
        // Clean up created file
        unlink($conf_path);
        return false;
    }

    // Save to database
    $iface_id = saveInterfaceToDatabase($iface, $address, $listen_port);

    if ($iface_id) {
        $success = "WireGuard interface '$iface' (ID: $iface_id) created, started, and saved to database.";

        $success_msg = "===============================\n";
        $success_msg .= "New WireGuard Interface Created\n";
        $success_msg .= "===============================\n";
        $success_msg .= "Interface Name: $iface\n";
        $success_msg .= "Interface ID: $iface_id\n";
        $success_msg .= "Address: $address\n";
        $success_msg .= "Listen Port: $listen_port\n";
        $success_msg .= "===============================\n";


        sendToTelegram($success_msg);
        return $iface_id;
    } else {
        $success = "WireGuard interface '$iface' created and started, but failed to save to database.";
        return true; // Interface created but DB failed
    }
}

// Function to generate interface configuration
function generateInterfaceConfig($private_key, $address, $listen_port)
{
    $conf = "[Interface]\n";
    $conf .= "PrivateKey = $private_key\n";
    $conf .= "Address = $address\n";
    $conf .= "ListenPort = $listen_port\n";
    $conf .= "SaveConfig = true\n\n";
    $conf .= "PostUp = ufw route allow in on wg0 out on eth0\n";
    $conf .= "PostUp = iptables -t nat -I POSTROUTING -o eth0 -j MASQUERADE\n";
    $conf .= "PreDown = ufw route delete allow in on wg0 out on eth0\n";
    $conf .= "PreDown = iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE\n";

    return $conf;
}

// Function to configure firewall
// Function to configure firewall
function configureFirewall($listen_port)
{
    exec("sudo ufw allow {$listen_port}/udp && sudo ufw reload 2>&1", $ufwOutput, $ufwCode);
    if ($ufwCode !== 0) {
        sendToTelegram("Error: Failed to configure UFW for port {$listen_port}. Error: " . implode("\n", $ufwOutput));
        return false;
    }

    return true;
}

// Function to start a WireGuard interface
function startInterface(string $iface): bool
{
    // Sanitize interface name (avoid injection)
    $iface = escapeshellarg($iface);

    exec("sudo /usr/bin/wg-quick up {$iface} 2>&1", $wgOutput, $wgCode);

    if ($wgCode !== 0) {
        sendToTelegram("Error: Failed to start WireGuard interface {$iface}. Error: " . implode("\n", $wgOutput));
        return false;
    }

    return true;
}

// Function to stop a WireGuard interface
function stopInterface(string $iface): bool
{
    // Sanitize interface name (avoid injection)
    $iface = escapeshellarg($iface);

    exec("sudo /usr/bin/wg-quick down {$iface} 2>&1", $wgOutput, $wgCode);

    if ($wgCode !== 0) {
        sendToTelegram("Error: Failed to stop WireGuard interface {$iface}. Error: " . implode("\n", $wgOutput));
        return false;
    }

    return true;
}

// Function to save interface to database
function saveInterfaceToDatabase($iface, $address, $listen_port)
{
    try {
        ensure_interfaces_table();
        $db = get_db();
        $iface_id = "IWG" . rand(10000, 99999);

        $stmt = $db->prepare('INSERT INTO interfaces (iface_id, name, address, port, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$iface_id, $iface, $address, $listen_port, 'active']);

        return $db->lastInsertId();
    } catch (Exception $e) {
        sendToTelegram("Error: Database error: " . $e->getMessage());
        return false;
    }
}

// Function to delete interface
function deleteWireGuardInterface($iface_id, $iface_name)
{
    global $error, $success;

    try {
        // Stop interface first
        stopInterface($iface_name);

        // Remove configuration file
        $conf_path = "/etc/wireguard/wg_$iface_name.conf";
        if (file_exists($conf_path)) {
            if (!unlink($conf_path)) {
                $error = "Failed to remove configuration file.";
                return false;
            }
        }

        // Remove from database
        $db = get_db();
        $stmt = $db->prepare('DELETE FROM interfaces WHERE iface_id = ?');
        $stmt->execute([$iface_id]);

        $success = "WireGuard interface '$iface_name' deleted successfully.";
        return true;
    } catch (Exception $e) {
        $error = "Failed to delete interface: " . $e->getMessage();
        sendToTelegram($error);
        return false;
    }
}

// Function to edit interface
function editWireGuardInterface($iface_id, $iface_name, $new_address, $new_port)
{
    global $error, $success;

    try {
        $db = get_db();

        // Update database
        $stmt = $db->prepare('UPDATE interfaces SET address = ?, port = ? WHERE iface_id = ?');
        $stmt->execute([$new_address, $new_port, $iface_id]);

        // Update configuration file
        $conf_path = "/etc/wireguard/wg_$iface_name.conf";
        if (file_exists($conf_path)) {
            $config = file_get_contents($conf_path);

            // Update address and port in configuration
            $config = preg_replace('/Address = .+/', "Address = $new_address", $config);
            $config = preg_replace('/ListenPort = .+/', "ListenPort = $new_port", $config);

            if (file_put_contents($conf_path, $config) === false) {
                $error = "Failed to update configuration file.";
                return false;
            }

            // Restart interface to apply changes
            stopInterface($iface_name);
            startInterface($iface_name);
        }

        $success = "WireGuard interface '$iface_name' updated successfully.";
        return true;
    } catch (Exception $e) {
        $error = "Failed to edit interface: " . $e->getMessage();
        sendToTelegram($error);
        return false;
    }
}

// Function to get interface by ID
function getInterfaceById($iface_id)
{
    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT * FROM interfaces WHERE iface_id = ?');
        $stmt->execute([$iface_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        sendToTelegram("Error: Database error: " . $e->getMessage());
        return false;
    }
}

// Function to get all interfaces
function getAllInterfaces()
{
    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT * FROM interfaces ORDER BY created_at DESC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        sendToTelegram("Error: Database error: " . $e->getMessage());
        return [];
    }
}

// Main create interface handler
if (isset($_POST['create_interface'])) {
    $iface = trim((string)($_POST['iface'] ?? ''));
    $private_key = trim((string)($_POST['private_key'] ?? ''));
    $listen_port = trim((string)($_POST['listen_port'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    createWireGuardInterface($iface, $private_key, $address, $listen_port);
}

// Delete interface handler
if (isset($_POST['delete_interface'])) {
    $iface_id = trim((string)($_POST['iface_id'] ?? ''));
    $iface_name = trim((string)($_POST['iface_name'] ?? ''));

    if ($iface_id && $iface_name) {
        deleteWireGuardInterface($iface_id, $iface_name);
    } else {
        $error = "Interface ID and name are required for deletion.";
        sendToTelegram("Error: " . $error);
    }
}

// Edit interface handler
if (isset($_POST['edit_interface'])) {
    $iface_id = trim((string)($_POST['iface_id'] ?? ''));
    $iface_name = trim((string)($_POST['iface_name'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $port = trim((string)($_POST['port'] ?? ''));

    if ($iface_id && $iface_name && $address && $port) {
        editWireGuardInterface($iface_id, $iface_name, $address, $port);
    } else {
        $error = "All fields are required for editing.";
        sendToTelegram("Error: " . $error);
    }
}
