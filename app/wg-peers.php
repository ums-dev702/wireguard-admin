<?php
require_once __DIR__ . '/../includes/header.php';

ensure_peers_table();

// Get available interfaces
$available_interfaces = get_available_interfaces();
$current_interface = $_GET['interface'] ?? '';

// Validate interface name (security check)
if (!in_array($current_interface, $available_interfaces)) {
    $current_interface = !empty($available_interfaces) ? $available_interfaces[0] : '';
}

?>

<style>
/* Custom styles for peer IP column */
.peer-ip-cell {
    background: rgba(59, 130, 246, 0.05);
    border-left: 2px solid rgba(59, 130, 246, 0.3);
}

.peer-ip-text {
    font-weight: 600;
    letter-spacing: 0.025em;
}

.peer-ip-copy {
    opacity: 0;
    transition: opacity 0.2s ease;
}

.peer-ip-cell:hover .peer-ip-copy {
    opacity: 1;
}

/* MikroTik dropdown styles */
.mikrotik-dropdown {
    transition: all 0.2s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(249, 115, 22, 0.3);
}

.mikrotik-dropdown button:hover {
    background-color: rgba(249, 115, 22, 0.1);
    color: rgba(249, 115, 22, 0.9);
}

.mikrotik-dropdown button:first-child:hover {
    border-radius: 0.5rem 0.5rem 0 0;
}

.mikrotik-dropdown button:last-child:hover {
    border-radius: 0 0 0.5rem 0.5rem;
}

@media (max-width: 768px) {
    .peer-ip-cell {
        background: rgba(59, 130, 246, 0.08);
    }
    
    .mikrotik-dropdown {
        position: fixed !important;
        right: 1rem !important;
        left: auto !important;
        width: auto !important;
        min-width: 12rem;
    }
}
</style>

<?php

// Function to get next available IP for an interface
function getNextAvailableIP($interface)
{
    try {
        $db = get_db();

        //remove wg_ prefix
        $interface = preg_replace('/^wg_/', '', $interface);

        // Get interface subnet from database
        $stmt = $db->prepare('SELECT address FROM interfaces WHERE name = ? LIMIT 1');
        $stmt->execute([$interface]);
        $interface_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$interface_data || empty($interface_data['address'])) {
            error_log("No interface found for {$interface}");
            return false;
        }

        // Extract IP and CIDR from interface address (e.g., 10.0.0.1/24)
        $address_parts = explode('/', $interface_data['address']);
        if (count($address_parts) !== 2) {
            throw new Exception("Invalid interface address format: {$interface_data['address']}");
        }

        $subnet_ip = $address_parts[0];
        $cidr = intval($address_parts[1]);

        // Validate CIDR
        if ($cidr < 8 || $cidr > 30) {
            throw new Exception("Invalid CIDR: {$cidr}. Must be between 8 and 30.");
        }

        // Calculate network address and usable range
        $ip_int = ip2long($subnet_ip);
        if ($ip_int === false) {
            throw new Exception("Invalid IP format: {$subnet_ip}");
        }

        // Calculate network mask and network address
        $host_bits = 32 - $cidr;
        $network_mask = ~((1 << $host_bits) - 1);
        $network_int = $ip_int & $network_mask;

        // Calculate usable IP range (skip network and broadcast addresses)
        $first_usable = $network_int + 1;
        $last_usable = $network_int + (1 << $host_bits) - 2;

        // The interface IP itself should be skipped
        $interface_ip_int = $ip_int;

        // Get all used IPs from peers
        $used_ips = [];
        try {
            $stmt = $db->prepare('SELECT allowed_ips FROM wg_peers');
            $stmt->execute();
            $peers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($peers as $peer) {
                if (!empty($peer['allowed_ips'])) {
                    // Extract only the IP part (e.g., 10.0.0.2/32 -> 10.0.0.2)
                    [$peer_ip] = explode('/', $peer['allowed_ips']);
                    $peer_ip_int = ip2long($peer_ip);
                    if ($peer_ip_int !== false && $peer_ip_int >= $first_usable && $peer_ip_int <= $last_usable) {
                        $used_ips[] = $peer_ip_int;
                    }
                }
            }
        } catch (Exception $e) {
            // If peers table doesn't exist or has different structure, continue with empty array
            error_log("Error getting used IPs: " . $e->getMessage());
            $used_ips = [];
        }

        // Find next available IP in the subnet range
        for ($ip_int = $first_usable; $ip_int <= $last_usable; $ip_int++) {
            // Skip the interface IP and already used IPs
            if ($ip_int !== $interface_ip_int && !in_array($ip_int, $used_ips)) {
                $next_ip = long2ip($ip_int);
                if ($next_ip !== false) {
                    return $next_ip . '/32';
                }
            }
        }

        // If no IP available in this subnet
        error_log("No available IPs in subnet {$subnet_ip}/{$cidr}");
        return false;
    } catch (Exception $e) {
        error_log("getNextAvailableIP error: " . $e->getMessage());
        return false;
    }
}


// Function to check if IP is already in use
function isIPInUse($ip)
{
    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM wg_peers WHERE allowed_ips = ?');
        $stmt->execute([$ip]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("isIPInUse error: " . $e->getMessage());
        return false;
    }
}

$success_message = '';
$error_message = '';

// Check for success/error messages from URL parameters
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}

// Check if interface is selected
if (empty($current_interface)) {
    $error_message = "Please select an interface first before managing peers.";
}

try {
    // Initialize WireGuard instance with selected interface
    if (!empty($current_interface)) {
        $wg_instance = new \WireGuardAdmin\WireGuard($db, $current_interface);
    }
    // Handle interface start/stop/restart
    if (isset($_POST['interface_action']) && !empty($current_interface)) {
        $action = $_POST['interface_action'];
        $user_id = $currentUser['id'] ?? null;

        try {
            $result = false;
            switch ($action) {
                case 'start':
                    $result = $wg_instance->startInterface();
                    $success_message = $result ? "Interface {$current_interface} started successfully!" : "Failed to start interface.";
                    break;
                case 'stop':
                    $result = $wg_instance->stopInterface();
                    $success_message = $result ? "Interface {$current_interface} stopped successfully!" : "Failed to stop interface.";
                    break;
                case 'restart':
                    $result = $wg_instance->restartInterface();
                    $success_message = $result ? "Interface {$current_interface} restarted successfully!" : "Failed to restart interface.";
                    break;
            }

            if ($result) {
                $auth->logActivity(
                    $user_id,
                    'INTERFACE_' . strtoupper($action),
                    "Interface {$current_interface} {$action}ed",
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                );
            }
        } catch (Exception $e) {
            $error_message = "Interface operation failed: " . $e->getMessage();
        }
    }

    // Get interface status and peers
    if (!empty($current_interface)) {
        $interface_status = $wg_instance->getStatus();
        $interface_running = $wg_instance->isRunning();
        $peers = $wg_instance->getPeers();
    } else {
        $interface_status = 'No interface selected';
        $interface_running = false;
        $peers = [];
    }
} catch (Exception $e) {
    $error_message = "Error initializing WireGuard interface: " . $e->getMessage();
    $interface_status = 'Error';
    $interface_running = false;
    $peers = [];
}
?>

<!-- WireGuard Peers Management -->
<div class="p-4 lg:p-6">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white mb-2">WireGuard Peers Management</h1>
            <p class="text-gray-400">Manage VPN peers across multiple interfaces</p>
            <div class="flex items-center gap-4 mt-2">
                <div class="text-sm text-gray-400">
                    Current Interface: <span class="text-white font-medium"><?= htmlspecialchars($current_interface) ?></span>
                </div>
                <div class="text-sm text-gray-400">
                    Available Interfaces: <span class="text-blue-400 font-medium"><?= count($available_interfaces) ?></span>
                </div>
            </div>
        </div>

        <!-- Interface Selector -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-300">Interface:</label>
                <select onchange="changeInterface(this.value)" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white text-sm min-w-24">
                    <?php foreach ($available_interfaces as $iface): ?>
                        <option value="<?= $iface ?>" <?= $iface === $current_interface ? 'selected' : '' ?>>
                            <?= htmlspecialchars($iface) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="create_interface" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm">
                <i class="fas fa-plus mr-2"></i>New Interface
            </a>
            <a href="port_forwarding.php" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors text-sm">
                <i class="fas fa-network-wired mr-2"></i>Port Forwarding
            </a>
            <button onclick="showCreatePeerModal()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-user-plus mr-2"></i>Add Peer
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="glass-card p-4 mb-6 border-l-4 border-green-500">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-400 mr-3"></i>
                <span class="text-green-400"><?= htmlspecialchars($success_message) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="glass-card p-4 mb-6 border-l-4 border-red-500">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-400 mr-3"></i>
                <span class="text-red-400"><?= htmlspecialchars($error_message) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Multi-Interface Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <?php
        try {
            ensure_interfaces_table();
            $db = get_db();
            
            // Try to get interface stats with status column first
            try {
                $interface_stats = $db->query('SELECT COUNT(*) as total, 
                                             SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active
                                             FROM interfaces')->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // If status column doesn't exist, just count all interfaces
                $interface_stats = $db->query('SELECT COUNT(*) as total, COUNT(*) as active FROM interfaces')->fetch(PDO::FETCH_ASSOC);
            }
            
            $total_peers = count($peers); // Current interface peers

            // Try to get total peers across all interfaces (if peer table exists)
            $all_peers = 0;
            try {
                $all_peers_result = $db->query('SELECT COUNT(*) as count FROM wg_peers WHERE status = "active"');
                if ($all_peers_result) {
                    $all_peers = $all_peers_result->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                }
            } catch (Exception $e) {
                $all_peers = $total_peers; // fallback to current interface peers
            }
        } catch (Exception $e) {
            $interface_stats = ['total' => count($available_interfaces), 'active' => 0];
            $total_peers = count($peers);
            $all_peers = $total_peers;
        }
        ?>

        <div class="glass-card p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 bg-blue-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-network-wired text-blue-400"></i>
                </div>
                <span class="text-2xl font-bold text-white"><?= $interface_stats['total'] ?></span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mb-1">Total Interfaces</h3>
            <p class="text-xs text-gray-500">Configured</p>
        </div>

        <div class="glass-card p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 bg-green-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <span class="text-2xl font-bold text-green-400"><?= $interface_stats['active'] ?></span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mb-1">Active Interfaces</h3>
            <p class="text-xs text-gray-500">Running</p>
        </div>

        <div class="glass-card p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 bg-purple-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-purple-400"></i>
                </div>
                <span class="text-2xl font-bold text-white"><?= $all_peers ?></span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mb-1">Total Peers</h3>
            <p class="text-xs text-gray-500">All interfaces</p>
        </div>

        <div class="glass-card p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 bg-yellow-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-friends text-yellow-400"></i>
                </div>
                <span class="text-2xl font-bold text-white"><?= $total_peers ?></span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mb-1">Current Interface</h3>
            <p class="text-xs text-gray-500"><?= htmlspecialchars($current_interface) ?> peers</p>
        </div>
    </div>

    <!-- Interface Status Card -->
    <div class="glass-card p-4 lg:p-6 mb-6">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div class="flex-1">
                <h2 class="text-lg font-semibold text-white mb-2">
                    Interface Status: <?= htmlspecialchars($current_interface) ?>
                    <a href="wg_status?interface=<?= urlencode($current_interface) ?>"
                        class="ml-2 text-sm text-blue-400 hover:text-blue-300">
                        <i class="fas fa-external-link-alt"></i> View Details
                    </a>
                </h2>
                <div class="flex items-center gap-6">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2 <?= $interface_running ? 'bg-green-400 animate-pulse' : 'bg-red-400' ?>"></div>
                        <span class="text-sm text-gray-300">
                            Status: <span class="<?= $interface_running ? 'text-green-400' : 'text-red-400' ?> font-medium">
                                <?= $interface_running ? 'Running' : 'Stopped' ?>
                            </span>
                        </span>
                    </div>
                    <div class="text-sm text-gray-400">
                        Peers: <span class="text-white font-medium"><?= count($peers) ?></span>
                    </div>
                    <div class="text-sm text-gray-400">
                        <a href="create_interface" class="text-blue-400 hover:text-blue-300">
                            <i class="fas fa-cog mr-1"></i>Manage Interfaces
                        </a>
                    </div>
                </div>
            </div>

            <!-- Interface Controls -->
            <div class="flex gap-2">
                <form method="POST" class="inline">
                    <input type="hidden" name="interface_action" value="start">
                    <button type="submit" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm transition-colors"
                        <?= $interface_running ? 'disabled' : '' ?>>
                        <i class="fas fa-play mr-1"></i>Start
                    </button>
                </form>
                <form method="POST" class="inline">
                    <input type="hidden" name="interface_action" value="stop">
                    <button type="submit" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm transition-colors"
                        <?= !$interface_running ? 'disabled' : '' ?>>
                        <i class="fas fa-stop mr-1"></i>Stop
                    </button>
                </form>
                <form method="POST" class="inline">
                    <input type="hidden" name="interface_action" value="restart">
                    <button type="submit" class="px-3 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-sm transition-colors">
                        <i class="fas fa-redo mr-1"></i>Restart
                    </button>
                </form>
                <a href="wg_status?interface=<?= urlencode($current_interface) ?>"
                    class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-sm transition-colors">
                    <i class="fas fa-chart-line mr-1"></i>Monitor
                </a>
            </div>
        </div>

        <!-- Interface Details -->
        <?php if ($interface_running && $interface_status !== 'Error'): ?>
            <div class="mt-4 p-3 bg-gray-800 rounded-lg">
                <pre class="text-sm text-gray-300 overflow-x-auto"><?= htmlspecialchars($interface_status) ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <!-- Peers Table -->
    <div class="glass-card overflow-hidden">
        <div class="px-4 lg:px-6 py-4 border-b border-gray-600">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-semibold text-white">
                        VPN Peers - <?= htmlspecialchars($current_interface) ?>
                        <span class="text-sm font-normal text-gray-400">(<?= count($peers) ?> peers)</span>
                    </h2>
                    <p class="text-sm text-gray-400 mt-1">
                        Managing peers for interface <?= htmlspecialchars($current_interface) ?>
                        <?php if (count($available_interfaces) > 1): ?>
                            • <a href="#" onclick="showInterfaceSelector()" class="text-blue-400 hover:text-blue-300">
                                Switch to other interface
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (!empty($peers)): ?>
                    <div class="text-right">
                        <button onclick="showCreatePeerModal()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm">
                            <i class="fas fa-user-plus mr-2"></i>Add Another Peer
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider hidden md:table-cell">Public Key</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Peer IP</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider hidden lg:table-cell">Allowed IPs</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-4 lg:px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider hidden md:table-cell">MikroTik</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider hidden sm:table-cell">Created</th>
                        <th class="px-4 lg:px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-600">
                    <?php if (empty($peers)): ?>
                        <tr>
                            <td colspan="8" class="px-4 lg:px-6 py-8 text-center text-gray-400">
                                <i class="fas fa-users-slash text-4xl mb-3 block"></i>
                                <p class="text-lg mb-2">No peers configured</p>
                                <p class="text-sm">Create your first VPN peer to get started.</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-router mr-1"></i>MikroTik RouterOS scripts will be available for each peer
                                </p>
                                <button onclick="showCreatePeerModal()" class="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                    <i class="fas fa-plus mr-2"></i>Add First Peer
                                </button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($peers as $peer): ?>
                            <tr class="hover:bg-gray-800/50 transition-colors">
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-500 bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-blue-400 text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-white"><?= htmlspecialchars($peer['name'] ?? 'Unnamed') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 lg:px-6 py-4 hidden md:table-cell">
                                    <div class="text-sm text-gray-300 font-mono">
                                        <?php if (!empty($peer['public_key'])): ?>
                                            <span title="<?= htmlspecialchars($peer['public_key']) ?>">
                                                <?= htmlspecialchars(substr($peer['public_key'], 0, 20)) ?>...
                                            </span>
                                            <button onclick="copyToClipboard('<?= htmlspecialchars($peer['public_key']) ?>')"
                                                class="ml-2 text-gray-400 hover:text-white transition-colors">
                                                <i class="fas fa-copy text-xs"></i>
                                            </button>
                                            <button onclick="showEditKeyModal(<?= $peer['id'] ?>, '<?= htmlspecialchars($peer['public_key'], ENT_QUOTES) ?>', '<?= htmlspecialchars($peer['name'], ENT_QUOTES) ?>')"
                                                class="ml-2 text-blue-400 hover:text-blue-300 transition-colors">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="showEditKeyModal(<?= $peer['id'] ?>, '', '<?= htmlspecialchars($peer['name'], ENT_QUOTES) ?>')"
                                                class="text-yellow-400 hover:text-yellow-300 transition-colors">
                                                <i class="fas fa-plus-circle mr-1"></i>
                                                <span class="italic">Add public key</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-300 peer-ip-cell">
                                    <?php 
                                    // Extract IP without /32 suffix using the helper function
                                    $peer_ip = extract_peer_ip($peer['allowed_ips'] ?? '');
                                    $full_allowed_ips = $peer['allowed_ips'] ?? 'N/A';
                                    ?>
                                    <div class="flex items-center">
                                        <span class="font-mono text-blue-300 peer-ip-text" 
                                              title="Full allowed IPs: <?= htmlspecialchars($full_allowed_ips) ?>"><?= htmlspecialchars($peer_ip) ?></span>
                                        <?php if ($peer_ip !== 'N/A'): ?>
                                            <button onclick="copyToClipboard('<?= htmlspecialchars($peer_ip) ?>')"
                                                class="ml-2 text-gray-400 hover:text-white transition-colors peer-ip-copy"
                                                title="Copy IP address">
                                                <i class="fas fa-copy text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-300 hidden lg:table-cell">
                                    <?= htmlspecialchars($peer['allowed_ips'] ?? 'N/A') ?>
                                </td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status = $peer['status'] ?? 'active';
                                    $status_color = $status === 'active' ? 'green' : 'red';
                                    ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-<?= $status_color ?>-500 bg-opacity-10 text-<?= $status_color ?>-400">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <!-- MikroTik Column -->
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-center hidden md:table-cell">
                                    <div class="flex justify-center gap-1">
                                        <button onclick="previewMikroTikScript(<?= $peer['id'] ?>, '<?= htmlspecialchars($peer['name'] ?? 'Unnamed', ENT_QUOTES) ?>')"
                                            class="px-2 py-1 bg-orange-600 hover:bg-orange-700 text-white rounded text-xs transition-colors"
                                            title="Preview MikroTik script">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="generateMikroTikScript(<?= $peer['id'] ?>)"
                                            class="px-2 py-1 bg-orange-700 hover:bg-orange-800 text-white rounded text-xs transition-colors"
                                            title="Download MikroTik script">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-400 hidden sm:table-cell">
                                    <?php if (isset($peer['created_at'])): ?>
                                        <?= date('M j, Y', strtotime($peer['created_at'])) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <div class="flex justify-end gap-2">
                                        <button onclick="deletePeer(<?= $peer['id'] ?>, '<?= htmlspecialchars($peer['name'] ?? 'Unnamed', ENT_QUOTES) ?>')"
                                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs transition-colors"
                                            title="Delete peer">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Peer Modal -->
<div id="createPeerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="glass-card p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-semibold text-white">Create New Peer</h3>
                <p class="text-sm text-gray-400">Adding to interface: <span class="text-blue-400 font-medium"><?= htmlspecialchars($current_interface) ?></span></p>
            </div>
            <button onclick="hideCreatePeerModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <?php if (empty($current_interface)): ?>
            <div class="bg-red-500 bg-opacity-10 border border-red-500 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                    <span class="text-red-400 text-sm">Please select an interface first before creating a peer.</span>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" id="createPeerForm" action="/app/backend/wg_peer_backend.php" class="space-y-4">
                <div class="space-y-4">
                    <input type="hidden" name="interface" value="<?= htmlspecialchars($current_interface) ?>">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($currentUser['id'] ?? '') ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Peer Name</label>
                        <input type="text" name="peer_name" required
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g., John's Phone">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Allowed IPs
                            <button type="button" onclick="generateNextIP()" class="ml-2 text-xs text-blue-400 hover:text-blue-300">
                                <i class="fas fa-magic"></i> Auto-generate
                            </button>
                        </label>
                        <input type="text" name="allowed_ips" required id="allowed_ips_input"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g., 10.0.0.2/32"
                            value="<?php
                                    $next_ip = getNextAvailableIP($current_interface);
                                    echo $next_ip ? htmlspecialchars($next_ip) : '';
                                    ?>">
                        <p class="text-xs text-gray-500 mt-1">IP address this peer can use (auto-generated from interface subnet)</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="hideCreatePeerModal()"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" name="create_peer"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Create Peer
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Public Key Modal -->
<div id="editKeyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="glass-card p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-semibold text-white">Update Public Key</h3>
                <p class="text-sm text-gray-400">Peer: <span id="editKeyPeerName" class="text-blue-400 font-medium"></span></p>
            </div>
            <button onclick="hideEditKeyModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="editKeyForm" action="/app/backend/wg_peer_backend.php" class="space-y-4">
            <input type="hidden" name="edit_public_key" value="1">
            <input type="hidden" name="interface" value="<?= htmlspecialchars($current_interface) ?>">
            <input type="hidden" name="peer_id" id="editKeyPeerId">
            
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">
                    Public Key
                </label>
                <textarea name="public_key" id="editKeyInput" required
                    class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                    rows="3" placeholder="Paste the WireGuard public key here..." onchange="updateWgCommand()"></textarea>
                <div id="wgCommandPreview" class="mt-2 p-2 bg-gray-900 rounded text-xs text-green-400 font-mono hidden">
                    <div class="text-gray-400 mb-1">Command that will be executed:</div>
                    <div id="wgCommandText"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">When you save this key, it will be automatically added to the WireGuard interface using the command shown above.</p>
            </div>

            <div id="privateKeySection" class="hidden">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                    Private Key (for client config)
                </label>
                <textarea id="generatedPrivateKey" readonly
                    class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white font-mono text-sm"
                    rows="3" placeholder="Generated private key will appear here..."></textarea>
                <button type="button" onclick="copyToClipboard(document.getElementById('generatedPrivateKey').value)"
                    class="mt-2 text-xs text-blue-400 hover:text-blue-300">
                    <i class="fas fa-copy mr-1"></i>Copy Private Key
                </button>
                <p class="text-xs text-gray-500 mt-1">Save this private key - it will be needed for the client configuration.</p>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="hideEditKeyModal()"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                    Cancel
                </button>
                <button type="submit" name="edit_public_key"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                    <i class="fas fa-save mr-2"></i>Save Key
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Peer Form (hidden) -->
<form id="deletePeerForm" method="POST" action="app/backend/wg_peer_backend.php" style="display: none;">
    <input type="hidden" name="delete_peer" value="1">
    <input type="hidden" name="interface" value="<?= htmlspecialchars($current_interface) ?>">
    <input type="hidden" name="peer_id" id="deletePeerId">
</form>

<!-- MikroTik Script Preview Modal -->
<div id="mikrotikPreviewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="glass-card p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-semibold text-white">MikroTik RouterOS Script Preview</h3>
                <p class="text-sm text-gray-400">Script for peer: <span id="previewPeerName" class="text-orange-400 font-medium"></span></p>
            </div>
            <div class="flex gap-2">
                <button onclick="downloadMikroTikScript()" class="px-3 py-1 bg-orange-600 hover:bg-orange-700 text-white rounded text-sm transition-colors">
                    <i class="fas fa-download mr-1"></i>Download
                </button>
                <button onclick="hideMikroTikPreview()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <div class="flex items-center gap-4 text-sm text-gray-400">
                <div class="flex items-center">
                    <i class="fas fa-router text-orange-400 mr-2"></i>
                    <span>RouterOS Script</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-network-wired text-blue-400 mr-2"></i>
                    <span>Interface: <?= htmlspecialchars($current_interface) ?></span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-copy text-green-400 mr-2"></i>
                    <button onclick="copyScriptToClipboard()" class="text-green-400 hover:text-green-300">
                        Copy to clipboard
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-gray-900 rounded-lg p-4 overflow-auto max-h-96 border border-gray-700">
            <pre id="mikrotikScriptContent" class="text-sm text-gray-300 font-mono whitespace-pre-wrap">Loading script preview...</pre>
        </div>

        <div class="mt-4 p-3 bg-blue-500 bg-opacity-10 border border-blue-500 rounded-lg">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-400 mr-2 mt-0.5"></i>
                <div class="text-sm text-blue-400">
                    <strong>Instructions:</strong>
                    <ol class="list-decimal list-inside mt-1 space-y-1 text-blue-300">
                        <li>Copy this script to your MikroTik RouterOS terminal</li>
                        <li>Run the script to configure WireGuard on your MikroTik device</li>
                        <li>The script will generate a public key - copy it</li>
                        <li>Add the generated public key to this peer's configuration</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function changeInterface(interfaceName) {
        if (interfaceName) {
            window.location.href = `?interface=${interfaceName}`;
        } else {
            alert('Please select a valid interface');
        }
    }

    function showInterfaceSelector() {
        const selector = document.querySelector('select[onchange*="changeInterface"]');
        if (selector) {
            selector.focus();
            // Optionally show a tooltip or highlight
            selector.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.5)';
            setTimeout(() => {
                selector.style.boxShadow = '';
            }, 2000);
        }
    }

    function showCreatePeerModal() {
        <?php if (empty($current_interface)): ?>
            alert('Please select an interface first before creating a peer.');
            return;
        <?php endif; ?>

        document.getElementById('createPeerModal').classList.remove('hidden');
        // Auto-generate next available IP when modal opens
        generateNextIP();
    }

    function hideCreatePeerModal() {
        document.getElementById('createPeerModal').classList.add('hidden');
        document.getElementById('createPeerForm').reset();
    }

    async function generateNextIP() {
        const input = document.getElementById('allowed_ips_input');
        if (!input) return;

        // Show loading
        input.value = 'Generating next available IP...';
        input.disabled = true;

        try {
            // Make AJAX call to get next available IP
            const api_url = window.location.origin + '/get_next_ip?interface=<?= urlencode($current_interface) ?>';
            console.log('Fetching next IP from:', api_url);
            const response = await fetch(api_url);
            if (response.ok) {
                const data = await response.json();
                console.log('Next IP response:', data);
                if (data.success) {
                    input.value = data.ip;
                } else {
                    // Use fallback IP if provided, otherwise use default
                    input.value = data.fallback || '10.0.0.2/32';
                    console.warn('IP generation failed:', data.error);
                }
            } else {
                throw new Error('Server error');
            }
        } catch (error) {
            console.error('Error generating IP:', error);
            // Fallback to PHP-generated IP
            const fallbackIP = '<?php
                                $next_ip = getNextAvailableIP($current_interface);
                                echo $next_ip ? htmlspecialchars($next_ip) : "10.0.0.2/32";
                                ?>';
            input.value = fallbackIP;
        } finally {
            input.disabled = false;
        }
    }

    function validateIP(ip) {
        // Enhanced IP validation
        const ipPattern = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(?:\/(?:3[0-2]|[12]?[0-9]))?$/;
        return ipPattern.test(ip);
    }

    async function checkIPAvailability(ip) {
        try {
            const response = await fetch('check_ip.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ip: ip
                })
            });

            if (response.ok) {
                const data = await response.json();
                return !data.inUse;
            }
        } catch (error) {
            console.error('Error checking IP:', error);
        }
        return true; // Assume available if check fails
    }

    function deletePeer(peerId, peerName) {
        if (confirm(`Are you sure you want to delete peer "${peerName}"?\n\nThis will:\n• Remove the peer from interface <?= htmlspecialchars($current_interface) ?>\n• Revoke access for this peer\n• Cannot be undone`)) {
            document.getElementById('deletePeerId').value = peerId;
            document.getElementById('deletePeerForm').submit();
        }
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            // Show temporary success message
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            toast.textContent = 'Copied to clipboard!';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('Copied to clipboard!');
        });
    }

    function downloadConfig(peerId) {
        // Generate and download the client config
        window.open(`download-config-simple.php?peer_id=${peerId}&interface=<?= urlencode($current_interface) ?>`, '_blank');
    }

    function showQRCode(peerId) {
        // Show QR code for mobile scanning
        window.open(`qr-code-simple.php?peer_id=${peerId}&interface=<?= urlencode($current_interface) ?>`, '_blank', 'width=900,height=700,scrollbars=yes,resizable=yes');
    }

    function generateMikroTikScript(peerId) {
        // Generate and download MikroTik RouterOS script
        const url = window.location.origin + '/generate_mikrotik_script?peer_id=${peerId}&interface=<?= urlencode($current_interface) ?>';
        console.log('Generating MikroTik script from URL:', url);
        // Create a temporary link to trigger download
        const link = document.createElement('a');
        link.href = url;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Show success message
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 bg-orange-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        toast.innerHTML = '<i class="fas fa-router mr-2"></i>MikroTik script downloaded!';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    let currentPreviewPeerId = null;

    async function previewMikroTikScript(peerId, peerName) {
        currentPreviewPeerId = peerId;
        
        // Show modal
        document.getElementById('mikrotikPreviewModal').classList.remove('hidden');
        document.getElementById('previewPeerName').textContent = peerName;
        
        // Load script content
        const scriptContent = document.getElementById('mikrotikScriptContent');
        scriptContent.textContent = 'Loading script preview...';
        
        // Validate parameters
        if (!peerId) {
            scriptContent.textContent = '# Error: No peer ID provided';
            scriptContent.className = 'text-sm text-red-400 font-mono whitespace-pre-wrap';
            return;
        }
        
        try {
            // Check if interface is selected
            const currentInterface = '<?= $current_interface ?>';
            if (!currentInterface) {
                throw new Error('No interface selected. Please select an interface first.');
            }
            const url = window.location.origin + '/generate_mikrotik_script?peer_id=' + encodeURIComponent(peerId) + '&interface=' + encodeURIComponent(currentInterface);
            console.log('Generating MikroTik script from URL:', url);
            const response = await fetch(url);
            console.log('Response status:', response.status);
            console.log('Response headers:', Object.fromEntries(response.headers.entries()));
            
            if (!response.ok) {
                const errorText = await response.text();
                console.log('Error response text:', errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}\n${errorText}`);
            }
            
            const scriptText = await response.text();
            console.log('Script loaded successfully, length:', scriptText.length);
            
            if (scriptText.trim() === '') {
                throw new Error('Empty response from server');
            }
            
            if (scriptText.startsWith('# Error:')) {
                scriptContent.textContent = scriptText;
                scriptContent.className = 'text-sm text-red-400 font-mono whitespace-pre-wrap';
            } else {
                scriptContent.textContent = scriptText;
                scriptContent.className = 'text-sm text-gray-300 font-mono whitespace-pre-wrap';
            }
        } catch (error) {
            console.error('Detailed error loading MikroTik script:', error);
            
            let errorMessage = '# Error: Failed to load script preview\n';
            errorMessage += `# ${error.message}\n\n`;
            errorMessage += '# Troubleshooting steps:\n';
            errorMessage += '# 1. Check if you are logged in\n';
            errorMessage += '# 2. Verify the peer exists in database\n';
            errorMessage += '# 3. Check browser console for detailed errors\n';
            errorMessage += '# 4. Ensure interface is selected\n';
            
            scriptContent.textContent = errorMessage;
            scriptContent.className = 'text-sm text-red-400 font-mono whitespace-pre-wrap';
        }
    }

    function hideMikroTikPreview() {
        document.getElementById('mikrotikPreviewModal').classList.add('hidden');
        currentPreviewPeerId = null;
    }

    function downloadMikroTikScript() {
        if (currentPreviewPeerId) {
            generateMikroTikScript(currentPreviewPeerId);
            hideMikroTikPreview();
        }
    }

    function copyScriptToClipboard() {
        const scriptContent = document.getElementById('mikrotikScriptContent').textContent;
        navigator.clipboard.writeText(scriptContent).then(() => {
            // Show success message
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            toast.innerHTML = '<i class="fas fa-copy mr-2"></i>Script copied to clipboard!';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = scriptContent;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('Script copied to clipboard!');
        });
    }

    // Close modal when clicking outside
    document.getElementById('createPeerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideCreatePeerModal();
        }
    });

    // Close Edit Key modal when clicking outside
    document.getElementById('editKeyModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideEditKeyModal();
        }
    });

    // Close MikroTik preview modal when clicking outside
    document.getElementById('mikrotikPreviewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideMikroTikPreview();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideCreatePeerModal();
            hideEditKeyModal();
            hideMikroTikPreview();
        }
    });

    // Enhanced form validation
    document.getElementById('createPeerForm').addEventListener('submit', async function(e) {
        const peerName = document.querySelector('input[name="peer_name"]').value.trim();
        const allowedIps = document.querySelector('input[name="allowed_ips"]').value.trim();

        if (!peerName) {
            alert('Please enter a peer name.');
            e.preventDefault();
            return;
        }

        if (!allowedIps) {
            alert('Please enter allowed IPs for this peer.');
            e.preventDefault();
            return;
        }

        // Validate IP format
        if (!validateIP(allowedIps)) {
            if (!confirm('The IP format appears to be incorrect. Continue anyway?')) {
                e.preventDefault();
                return;
            }
        }

        // Check if IP is already in use
        const isAvailable = await checkIPAvailability(allowedIps);
        if (!isAvailable) {
            alert('This IP address is already in use by another peer. Please choose a different IP or click "Auto-generate" for the next available IP.');
            e.preventDefault();
            return;
        }

        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
        submitBtn.disabled = true;

        // Re-enable if form submission fails
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 5000);
    });

    // Edit Key Modal Functions
    function showEditKeyModal(peerId, currentKey, peerName) {
        document.getElementById('editKeyPeerId').value = peerId;
        document.getElementById('editKeyInput').value = currentKey || '';
        document.getElementById('editKeyPeerName').textContent = peerName;
        document.getElementById('editKeyModal').classList.remove('hidden');
        
        // Hide private key section initially
        document.getElementById('privateKeySection').classList.add('hidden');
        
        // Update command preview if there's already a key
        updateWgCommand();
    }

    function hideEditKeyModal() {
        document.getElementById('editKeyModal').classList.add('hidden');
        document.getElementById('editKeyForm').reset();
        document.getElementById('privateKeySection').classList.add('hidden');
        document.getElementById('wgCommandPreview').classList.add('hidden');
    }

    function updateWgCommand() {
        const publicKey = document.getElementById('editKeyInput').value.trim();
        const commandPreview = document.getElementById('wgCommandPreview');
        const commandText = document.getElementById('wgCommandText');
        
        if (publicKey && publicKey.length > 20) {
            // Get the current interface from the URL or page context
            const currentInterface = '<?= htmlspecialchars($current_interface) ?>';
            const actualInterface = currentInterface.replace('wg_', '');
            
            // Get the peer's allowed IPs (this would need to be passed to the modal or fetched)
            // For now, we'll show a placeholder
            const allowedIps = getCurrentPeerAllowedIPs() || 'PEER_ALLOWED_IPS';
            
            const command = `sudo wg set wg_${actualInterface} peer ${publicKey} allowed-ips ${allowedIps}`;
            commandText.textContent = command;
            commandPreview.classList.remove('hidden');
        } else {
            commandPreview.classList.add('hidden');
        }
    }

    function getCurrentPeerAllowedIPs() {
        // This function should get the current peer's allowed IPs
        // For now, we'll return a placeholder - in a real implementation,
        // you'd pass this data to the modal or fetch it via AJAX
        const peerName = document.getElementById('editKeyPeerName').textContent;
        
        // Look for the peer in the current page's table to get allowed IPs
        const rows = document.querySelectorAll('tbody tr');
        for (let row of rows) {
            const nameCell = row.querySelector('td:first-child .text-white');
            if (nameCell && nameCell.textContent.trim() === peerName) {
                const allowedIpsCell = row.querySelector('td:nth-child(4)'); // Allowed IPs column
                if (allowedIpsCell) {
                    return allowedIpsCell.textContent.trim();
                }
            }
        }
        
        return null;
    }


    // Auto-generate IP when interface changes
    <?php if (!empty($current_interface)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Pre-populate IP when page loads
            const ipInput = document.getElementById('allowed_ips_input');
            if (ipInput && ipInput.value === 'Loading next available IP...') {
                generateNextIP();
            }
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>