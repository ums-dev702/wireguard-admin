<?php 
require_once __DIR__ . '/../includes/header.php';

// Get available interfaces
$available_interfaces = get_available_interfaces();
$current_interface = $_GET['interface'] ?? WG_IFACE;

// Validate interface name (security check)
if (!in_array($current_interface, $available_interfaces)) {
    $current_interface = WG_IFACE;
}

$success_message = '';
$error_message = '';

try {
    // Initialize WireGuard instance with selected interface
    $wg_instance = new \WireGuardAdmin\WireGuard($db, $current_interface);
    
    // Handle peer creation
    if (isset($_POST['create_peer'])) {
        $peer_name = trim($_POST['peer_name'] ?? '');
        $allowed_ips = trim($_POST['allowed_ips'] ?? '');
        $dns_servers = trim($_POST['dns_servers'] ?? '8.8.8.8,1.1.1.1');
        $user_id = $currentUser['id'] ?? null;

        if (empty($peer_name) || empty($allowed_ips)) {
            $error_message = "Peer name and allowed IPs are required.";
        } else {
            try {
                $peer_data = $wg_instance->createPeer($peer_name, $allowed_ips, $dns_servers);
                $success_message = "Peer '{$peer_name}' created successfully!";
                
                // Log activity
                $auth->logActivity(
                    $user_id, 
                    'CREATE_PEER', 
                    "Created WireGuard peer: {$peer_name} on interface {$current_interface}",
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                );
            } catch (Exception $e) {
                $error_message = "Failed to create peer: " . $e->getMessage();
            }
        }
    }

    // Handle peer removal
    if (isset($_POST['delete_peer'])) {
        $peer_id = intval($_POST['peer_id']);
        $user_id = $currentUser['id'] ?? null;

        try {
            $peer = $wg_instance->getPeer($peer_id);
            if ($peer) {
                $wg_instance->deletePeer($peer_id);
                $success_message = "Peer '{$peer['name']}' removed successfully!";
                
                // Log activity
                $auth->logActivity(
                    $user_id, 
                    'DELETE_PEER', 
                    "Deleted WireGuard peer: {$peer['name']} on interface {$current_interface}",
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                );
            } else {
                $error_message = "Peer not found.";
            }
        } catch (Exception $e) {
            $error_message = "Failed to remove peer: " . $e->getMessage();
        }
    }

    // Handle interface start/stop/restart
    if (isset($_POST['interface_action'])) {
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
    $interface_status = $wg_instance->getStatus();
    $interface_running = $wg_instance->isRunning();
    $peers = $wg_instance->getPeers();

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
            $interface_stats = $db->query('SELECT COUNT(*) as total, 
                                         SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active
                                         FROM interfaces')->fetch(PDO::FETCH_ASSOC);
            $total_peers = count($peers); // Current interface peers
            
            // Try to get total peers across all interfaces (if peer table exists)
            $all_peers = 0;
            try {
                $all_peers_result = $db->query('SELECT COUNT(*) as count FROM peers WHERE status = "active"');
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
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Public Key</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Allowed IPs</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Created</th>
                        <th class="px-4 lg:px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-600">
                    <?php if (empty($peers)): ?>
                    <tr>
                        <td colspan="6" class="px-4 lg:px-6 py-8 text-center text-gray-400">
                            <i class="fas fa-users-slash text-4xl mb-3 block"></i>
                            <p class="text-lg mb-2">No peers configured</p>
                            <p class="text-sm">Create your first VPN peer to get started.</p>
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
                                    <div class="text-xs text-gray-400">ID: <?= $peer['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 lg:px-6 py-4">
                            <div class="text-sm text-gray-300 font-mono">
                                <span title="<?= htmlspecialchars($peer['public_key']) ?>">
                                    <?= htmlspecialchars(substr($peer['public_key'], 0, 20)) ?>...
                                </span>
                                <button onclick="copyToClipboard('<?= htmlspecialchars($peer['public_key']) ?>')" 
                                        class="ml-2 text-gray-400 hover:text-white transition-colors">
                                    <i class="fas fa-copy text-xs"></i>
                                </button>
                            </div>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-300">
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
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                            <?php if (isset($peer['created_at'])): ?>
                                <?= date('M j, Y', strtotime($peer['created_at'])) ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-right text-sm">
                            <div class="flex justify-end gap-2">
                                <button onclick="downloadConfig(<?= $peer['id'] ?>)" 
                                        class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs transition-colors">
                                    <i class="fas fa-download mr-1"></i>Config
                                </button>
                                <button onclick="showQRCode(<?= $peer['id'] ?>)" 
                                        class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs transition-colors">
                                    <i class="fas fa-qrcode mr-1"></i>QR
                                </button>
                                <button onclick="deletePeer(<?= $peer['id'] ?>, '<?= htmlspecialchars($peer['name'] ?? 'Unnamed', ENT_QUOTES) ?>')" 
                                        class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs transition-colors">
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
            <h3 class="text-lg font-semibold text-white">Create New Peer</h3>
            <button onclick="hideCreatePeerModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="createPeerForm">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Peer Name</label>
                    <input type="text" name="peer_name" required 
                           class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., John's Phone">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Allowed IPs</label>
                    <input type="text" name="allowed_ips" required 
                           class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., 10.0.0.2/32">
                    <p class="text-xs text-gray-500 mt-1">IP address(es) this peer can use</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">DNS Servers</label>
                    <input type="text" name="dns_servers" 
                           class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500"
                           value="8.8.8.8,1.1.1.1"
                           placeholder="8.8.8.8,1.1.1.1">
                    <p class="text-xs text-gray-500 mt-1">Comma-separated DNS servers</p>
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
    </div>
</div>

<!-- Delete Peer Form (hidden) -->
<form id="deletePeerForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_peer" value="1">
    <input type="hidden" name="peer_id" id="deletePeerId">
</form>

<script>
function changeInterface(interfaceName) {
    window.location.href = `?interface=${interfaceName}`;
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
    document.getElementById('createPeerModal').classList.remove('hidden');
    // Auto-suggest next available IP
    const allowedIpsInput = document.querySelector('input[name="allowed_ips"]');
    if (allowedIpsInput && allowedIpsInput.value === '') {
        suggestNextIP();
    }
}

function hideCreatePeerModal() {
    document.getElementById('createPeerModal').classList.add('hidden');
    document.getElementById('createPeerForm').reset();
}

function suggestNextIP() {
    // This could be enhanced to actually check for the next available IP
    // For now, just suggest a common pattern
    const input = document.querySelector('input[name="allowed_ips"]');
    if (input) {
        // You could make an AJAX call here to get the next available IP
        const baseIP = '10.0.0.';
        const suggestedIP = baseIP + (Math.floor(Math.random() * 200) + 10) + '/32';
        input.placeholder = `Suggested: ${suggestedIP}`;
    }
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
    // This would generate and download the client config
    window.open(`download-config.php?peer_id=${peerId}&interface=<?= urlencode($current_interface) ?>`, '_blank');
}

function showQRCode(peerId) {
    // This would show QR code for mobile scanning
    window.open(`qr-code.php?peer_id=${peerId}&interface=<?= urlencode($current_interface) ?>`, '_blank', 'width=400,height=400');
}

// Close modal when clicking outside
document.getElementById('createPeerModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideCreatePeerModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideCreatePeerModal();
    }
});

// Form validation
document.getElementById('createPeerForm').addEventListener('submit', function(e) {
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
    
    // Basic IP validation
    const ipPattern = /^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/;
    if (!ipPattern.test(allowedIps)) {
        if (!confirm('The IP format might be incorrect. Continue anyway?')) {
            e.preventDefault();
            return;
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>