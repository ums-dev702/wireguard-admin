<?php
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../autoloader.php';
include_once __DIR__ . '/../../includes/functions.php';

function redirectToCreateInterface(string $type, string $message): void
{
    header('Location: ../../create_interface?' . $type . '=' . urlencode($message));
    exit;
}

function exactCommandError(string $label, array $output, int $code): string
{
    $message = trim(implode("\n", $output));
    if ($message === '') {
        $message = 'No command output returned.';
    }

    return "{$label} failed with exit code {$code}: {$message}";
}

function normalizeInterfaceName(string $iface): string
{
    $iface = trim($iface);
    $iface = preg_replace('/^wg_/', '', $iface);

    if (!preg_match('/^[A-Za-z0-9_-]{1,8}$/', $iface)) {
        return '';
    }

    return $iface;
}

function wireGuardDeviceName(string $iface): string
{
    return 'wg_' . normalizeInterfaceName($iface);
}

function interfaceExistsInDatabase(string $iface): bool
{
    try {
        ensure_interfaces_table();
        $db = get_db();
        $stmt = $db->prepare('SELECT COUNT(*) FROM interfaces WHERE name = ?');
        $stmt->execute([$iface]);

        return (int) $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        sendToTelegram("Error checking interface existence: " . $e->getMessage());
        return false;
    }
}

// Function to handle interface creation
function createWireGuardInterface($iface, $private_key, $address, $listen_port)
{
    global $error, $success;

    $iface = normalizeInterfaceName((string)$iface);
    $listen_port = (int)$listen_port;

    // Validation
    if ($iface === '') {
        $error = "Interface name must be 1-8 characters and contain only letters, numbers, underscores, or dashes.";
        sendToTelegram("Error: " . $error);
        return false;
    }

    if (!$private_key || !$address) {
        $error = "All required fields must be filled.";
        sendToTelegram("Error: " . $error);
        return false;
    }

    // Validate the listen port comprehensively
    $portValidation = validate_wireguard_port((int)$listen_port);
    if (!$portValidation['valid']) {
        $error = "Port validation failed: " . $portValidation['message'];
        sendToTelegram("Error: " . $error);
        return false;
    }

    if (interfaceExistsInDatabase($iface)) {
        $error = "Interface already exists in the database.";
        sendToTelegram("Error: " . $error);
        return false;
    }

    $wg_iface = wireGuardDeviceName($iface);
    $conf_path = "/etc/wireguard/{$wg_iface}.conf";

    // Check if interface already exists
    if (file_exists($conf_path)) {
        $error = "Interface configuration already exists.";
        sendToTelegram("Error: " . $error);
        return false;
    }

    // Create configuration
    $conf = generateInterfaceConfig($iface, $private_key, $address, $listen_port);

    // Write configuration file
    if (file_put_contents($conf_path, $conf) === false) {
        $lastError = error_get_last();
        $error = "Failed to write configuration file {$conf_path}: " . ($lastError['message'] ?? 'Unknown file write error.');
        sendToTelegram("Error: " . $error);
        return false;
    }
    if (!chmod($conf_path, 0600)) {
        $lastError = error_get_last();
        $error = "Failed to set permissions on {$conf_path}: " . ($lastError['message'] ?? 'Unknown chmod error.');
        sendToTelegram("Error: " . $error);
        unlink($conf_path);
        return false;
    }

    // Configure firewall
    if (!configureFirewall($listen_port)) {
        sendToTelegram("Error: " . $error);
        // Clean up created file
        unlink($conf_path);
        return false;
    }

    // Start interface
    if (!startInterface($iface)) {
        sendToTelegram("Error: " . $error);
        // Clean up created file
        unlink($conf_path);
        // Remove firewall rule
        configureFirewallRemove($listen_port);
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
        $success_msg .= "Port Status: " . $portValidation['message'] . "\n";
        $success_msg .= "===============================\n";

        sendToTelegram($success_msg);
        return $iface_id;
    } else {
        stopInterface($iface);
        unlink($conf_path);
        configureFirewallRemove($listen_port);

        $error = "Interface was created but could not be saved to the database, so it was rolled back.";
        sendToTelegram("Error: " . $error);
        return false;
    }
}

// Function to generate interface configuration
function generateInterfaceConfig($iface, $private_key, $address, $listen_port)
{
    $wg_iface = wireGuardDeviceName($iface);
    $conf = "[Interface]\n";
    $conf .= "PrivateKey = $private_key\n";
    $conf .= "Address = $address\n";
    $conf .= "ListenPort = $listen_port\n";
    $conf .= "SaveConfig = true\n\n";
    $conf .= "PostUp = ufw route allow in on {$wg_iface} out on eth0\n";
    $conf .= "PostUp = iptables -t nat -I POSTROUTING -o eth0 -j MASQUERADE\n";
    $conf .= "PreDown = ufw route delete allow in on {$wg_iface} out on eth0\n";
    $conf .= "PreDown = iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE\n";

    return $conf;
}

// Function to configure firewall
// Function to configure firewall
function configureFirewall($listen_port)
{
    global $error;

    $listen_port = (int)$listen_port;
    exec("sudo ufw allow {$listen_port}/udp && sudo ufw reload 2>&1", $ufwOutput, $ufwCode);
    if ($ufwCode !== 0) {
        $error = exactCommandError("Configuring UFW for UDP port {$listen_port}", $ufwOutput, $ufwCode);
        sendToTelegram("Error: " . $error);
        return false;
    }

    return true;
}

function configureFirewallRemove($listen_port)
{
    global $error;

    $listen_port = (int)$listen_port;
    exec("sudo ufw delete allow {$listen_port}/udp && sudo ufw reload 2>&1", $ufwOutput, $ufwCode);
    if ($ufwCode !== 0) {
        $error = exactCommandError("Removing UFW rule for UDP port {$listen_port}", $ufwOutput, $ufwCode);
        sendToTelegram("Error: " . $error);
        return false;
    }

    return true;
}


// Function to start a WireGuard interface
function startInterface(string $iface): bool
{
    global $error;

    $wg_iface = wireGuardDeviceName($iface);
    if ($wg_iface === 'wg_') {
        $error = "Invalid WireGuard interface name.";
        sendToTelegram("Error: " . $error);
        return false;
    }

    exec("sudo /usr/bin/wg-quick up " . escapeshellarg($wg_iface) . " 2>&1", $wgOutput, $wgCode);

    if ($wgCode !== 0) {
        $error = exactCommandError("Starting WireGuard interface {$wg_iface}", $wgOutput, $wgCode);
        sendToTelegram("Error: " . $error);
        return false;
    }

    return true;
}

// Function to stop a WireGuard interface
function stopInterface(string $iface): bool
{
    global $error;

    $wg_iface = wireGuardDeviceName($iface);
    if ($wg_iface === 'wg_') {
        $error = "Invalid WireGuard interface name.";
        sendToTelegram("Error: " . $error);
        return false;
    }

    exec("sudo /usr/bin/wg-quick down " . escapeshellarg($wg_iface) . " 2>&1", $wgOutput, $wgCode);

    if ($wgCode !== 0) {
        $error = exactCommandError("Stopping WireGuard interface {$wg_iface}", $wgOutput, $wgCode);
        sendToTelegram("Error: " . $error);
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
        
        // Generate unique interface ID
        do {
            $iface_id = "IWG" . rand(10000, 99999);
            // Check if this ID already exists
            $check_stmt = $db->prepare('SELECT COUNT(*) FROM interfaces WHERE iface_id = ?');
            $check_stmt->execute([$iface_id]);
            $exists = $check_stmt->fetchColumn() > 0;
        } while ($exists);

        $stmt = $db->prepare('INSERT INTO interfaces (iface_id, name, address, port, status) VALUES (?, ?, ?, ?, ?)');
        $result = $stmt->execute([$iface_id, $iface, $address, $listen_port, 'active']);
        
        if ($result) {
            return $iface_id; // Return the generated interface ID, not the auto-increment ID
        } else {
            $error = "Failed to insert interface into database.";
            sendToTelegram("Error: " . $error);
            return false;
        }
    } catch (Exception $e) {
        $error = "Database error while saving interface: " . $e->getMessage();
        sendToTelegram("Error: " . $error);
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
        $conf_path = "/etc/wireguard/" . wireGuardDeviceName($iface_name) . ".conf";
        if (file_exists($conf_path)) {
            if (!unlink($conf_path)) {
                $lastError = error_get_last();
                $error = "Failed to remove configuration file {$conf_path}: " . ($lastError['message'] ?? 'Unknown unlink error.');
                return false;
            }
        }

  

        // Remove from database
        $db = get_db();

        // Get listen_port from database before deleting
        $stmt = $db->prepare('SELECT port FROM interfaces WHERE iface_id = ?');
        $stmt->execute([$iface_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $listen_port = $row ? $row['port'] : null;

        // Delete interface from database
        $stmt = $db->prepare('DELETE FROM interfaces WHERE iface_id = ?');
        $stmt->execute([$iface_id]);

        // Remove firewall rule if port is available
        if ($listen_port) {
            configureFirewallRemove($listen_port);
        }

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

        // Get current port to check if it's changing
        $stmt = $db->prepare('SELECT port FROM interfaces WHERE iface_id = ?');
        $stmt->execute([$iface_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_port = $row ? $row['port'] : null;

        // If port is changing, validate the new port
        if ($current_port && $current_port != $new_port) {
            $portValidation = validate_wireguard_port((int)$new_port);
            if (!$portValidation['valid']) {
                $error = "New port validation failed: " . $portValidation['message'];
                sendToTelegram("Error: " . $error);
                return false;
            }
        }

        // Update database
        $stmt = $db->prepare('UPDATE interfaces SET address = ?, port = ? WHERE iface_id = ?');
        $stmt->execute([$new_address, $new_port, $iface_id]);

        // Update configuration file
        $conf_path = "/etc/wireguard/" . wireGuardDeviceName($iface_name) . ".conf";
        if (file_exists($conf_path)) {
            $config = file_get_contents($conf_path);

            // Update address and port in configuration
            $config = preg_replace('/Address = .+/', "Address = $new_address", $config);
            $config = preg_replace('/ListenPort = .+/', "ListenPort = $new_port", $config);

            if (file_put_contents($conf_path, $config) === false) {
                $lastError = error_get_last();
                $error = "Failed to update configuration file {$conf_path}: " . ($lastError['message'] ?? 'Unknown file write error.');
                return false;
            }

            // If port has changed, update firewall rules
            if ($current_port && $current_port !== $new_port) {
                // Remove old firewall rule
                configureFirewallRemove($current_port);
                // Add new firewall rule
                if (!configureFirewall($new_port)) {
                    $error = "Failed to configure firewall for new port $new_port.";
                    // Try to restore old rule
                    configureFirewall($current_port);
                    return false;
                }
            }

            // Restart interface to apply changes
            if (!stopInterface($iface_name)) {
                return false;
            }

            if (!startInterface($iface_name)) {
                return false;
            }
        }

        $success = "WireGuard interface '$iface_name' updated successfully.";
        
        $success_msg = "===============================\n";
        $success_msg .= "WireGuard Interface Updated\n";
        $success_msg .= "===============================\n";
        $success_msg .= "Interface Name: $iface_name\n";
        $success_msg .= "Interface ID: $iface_id\n";
        $success_msg .= "New Address: $new_address\n";
        $success_msg .= "New Port: $new_port\n";
        if ($current_port && $current_port !== $new_port) {
            $success_msg .= "Port changed from: $current_port to $new_port\n";
        }
        $success_msg .= "===============================\n";
        
        sendToTelegram($success_msg);
        return true;
    } catch (Throwable $e) {
        $error = "Failed to edit interface: " . get_class($e) . ': ' . $e->getMessage();
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

try {
    $action = $_POST['action'] ?? '';

    // Main create interface handler
    if ($action === 'create_interface' || isset($_POST['create_interface'])) {
        $iface = trim((string)($_POST['iface'] ?? ''));
        $private_key = trim((string)($_POST['private_key'] ?? ''));
        $listen_port = trim((string)($_POST['listen_port'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        $createWireGuardInterface = createWireGuardInterface($iface, $private_key, $address, $listen_port);
        if ($createWireGuardInterface) {
            redirectToCreateInterface('success', 'WireGuard interface created successfully.');
        }

        redirectToCreateInterface('error', 'Failed to create WireGuard interface. ' . ($error ?? 'Unknown error.'));
    }

    // Delete interface handler
    if ($action === 'delete_interface' || isset($_POST['delete_interface'])) {
        $iface_id = trim((string)($_POST['iface_id'] ?? ''));
        $iface_name = trim((string)($_POST['iface_name'] ?? ''));

        if ($iface_id && $iface_name) {
            if (deleteWireGuardInterface($iface_id, $iface_name)) {
                redirectToCreateInterface('success', 'Interface deleted successfully.');
            }

            redirectToCreateInterface('error', 'Failed to delete interface. ' . ($error ?? 'Unknown error.'));
        }

        $error = "Interface ID and name are required for deletion.";
        sendToTelegram("Error: " . $error);
        redirectToCreateInterface('error', $error);
    }

    // Edit interface handler
    if ($action === 'edit_interface' || isset($_POST['edit_interface'])) {
        $iface_id = trim((string)($_POST['iface_id'] ?? ''));
        $iface_name = trim((string)($_POST['iface_name'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $port = trim((string)($_POST['port'] ?? ''));

        if ($iface_id && $iface_name && $address && $port) {
            $result = editWireGuardInterface($iface_id, $iface_name, $address, $port);
            if ($result) {
                redirectToCreateInterface('success', 'Interface updated successfully.');
            }

            redirectToCreateInterface('error', 'Failed to update interface. ' . ($error ?? 'Unknown error.'));
        }

        $error = "All fields are required for editing.";
        sendToTelegram("Error: " . $error);
        redirectToCreateInterface('error', $error);
    }

    // Handle delete by ID (from interface table)
    if (isset($_POST['delete_id'])) {
        $delete_id = trim((string)($_POST['delete_id'] ?? ''));

        if (!$delete_id) {
            redirectToCreateInterface('error', 'Invalid interface ID.');
        }

        $db = get_db();
        $stmt = $db->prepare('SELECT * FROM interfaces WHERE id = ?');
        $stmt->execute([$delete_id]);
        $interface = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$interface) {
            redirectToCreateInterface('error', 'Interface not found.');
        }

        $result = deleteWireGuardInterface($interface['iface_id'], $interface['name']);
        if ($result) {
            redirectToCreateInterface('success', 'Interface deleted successfully.');
        }

        redirectToCreateInterface('error', 'Failed to delete interface. ' . ($error ?? 'Unknown error.'));
    }

    redirectToCreateInterface('error', 'No valid interface action was submitted.');
} catch (Throwable $e) {
    $exactError = get_class($e) . ': ' . $e->getMessage();
    sendToTelegram("Unhandled create interface backend error: " . $exactError);
    redirectToCreateInterface('error', $exactError);
}
