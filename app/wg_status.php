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
    
    // Handle interface actions
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
                case 'reload':
                    // Update peer stats and reload configuration
                    $wg_instance->updatePeerStats();
                    $success_message = "Interface configuration reloaded successfully!";
                    $result = true;
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

    // Get comprehensive status information
    $interface_status = $wg_instance->getStatus();
    $interface_running = $wg_instance->isRunning();
    $peers = $wg_instance->getPeers();
    $system_stats = $wg_instance->getSystemStats();
    
    // Parse WireGuard status for detailed information
    $wg_details = parseWireGuardStatus($interface_status, $current_interface);
    
    // Get network statistics
    $network_stats = getNetworkInterfaceStats($current_interface);
    
} catch (Exception $e) {
    $error_message = "Error initializing WireGuard interface: " . $e->getMessage();
    $interface_status = 'Error';
    $interface_running = false;
    $peers = [];
    $system_stats = [];
    $wg_details = [];
    $network_stats = [];
}

// Helper functions
function parseWireGuardStatus($status, $interface) {
    $details = [
        'interface' => $interface,
        'public_key' => 'N/A',
        'listen_port' => 'N/A',
        'peer_count' => 0,
        'last_handshake' => 'N/A'
    ];
    
    if (empty($status) || $status === 'Interface not running') {
        return $details;
    }
    
    $lines = explode("\n", $status);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'public key:') !== false) {
            $details['public_key'] = trim(str_replace('public key:', '', $line));
        } elseif (strpos($line, 'listening port:') !== false) {
            $details['listen_port'] = trim(str_replace('listening port:', '', $line));
        } elseif (strpos($line, 'peer:') !== false) {
            $details['peer_count']++;
        }
    }
    
    return $details;
}

function getNetworkInterfaceStats($interface) {
    $stats = [
        'rx_bytes' => 0,
        'tx_bytes' => 0,
        'rx_packets' => 0,
        'tx_packets' => 0,
        'mtu' => 'N/A',
        'state' => 'UNKNOWN'
    ];
    
    // Try to get stats from /sys/class/net/ (Linux)
    $stats_path = "/sys/class/net/{$interface}/statistics/";
    if (file_exists($stats_path . 'rx_bytes')) {
        $stats['rx_bytes'] = intval(file_get_contents($stats_path . 'rx_bytes'));
        $stats['tx_bytes'] = intval(file_get_contents($stats_path . 'tx_bytes'));
        $stats['rx_packets'] = intval(file_get_contents($stats_path . 'rx_packets'));
        $stats['tx_packets'] = intval(file_get_contents($stats_path . 'tx_packets'));
    }
    
    // Get MTU and state
    $ip_output = shell_exec("ip link show {$interface} 2>/dev/null");
    if ($ip_output) {
        if (preg_match('/mtu (\d+)/', $ip_output, $matches)) {
            $stats['mtu'] = $matches[1];
        }
        if (strpos($ip_output, 'state UP') !== false) {
            $stats['state'] = 'UP';
        } elseif (strpos($ip_output, 'state DOWN') !== false) {
            $stats['state'] = 'DOWN';
        }
    }
    
    return $stats;
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>

<!-- WireGuard Status Page -->
<div class="p-4 lg:p-6">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white mb-2">WireGuard Status</h1>
            <p class="text-gray-400">Monitor interface status, statistics, and performance</p>
        </div>
        
        <!-- Interface Selector & Auto Refresh -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-300">Interface:</label>
                <select onchange="changeInterface(this.value)" class="px-3 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm">
                    <?php foreach ($available_interfaces as $iface): ?>
                    <option value="<?= $iface ?>" <?= $iface === $current_interface ? 'selected' : '' ?>>
                        <?= $iface ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="refreshStatus()" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm transition-colors">
                    <i class="fas fa-sync-alt mr-1"></i>Refresh
                </button>
                <label class="flex items-center text-sm text-gray-300">
                    <input type="checkbox" id="autoRefresh" onchange="toggleAutoRefresh()" class="mr-2">
                    Auto (30s)
                </label>
            </div>
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

    <!-- Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-5 mb-6">
        <!-- Interface Status -->
        <div class="glass-card p-4 lg:p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-network-wired text-blue-400 text-lg lg:text-xl"></i>
                </div>
                <div class="text-right">
                    <div class="flex items-center justify-end mb-1">
                        <div class="w-3 h-3 rounded-full mr-2 <?= $interface_running ? 'bg-green-400' : 'bg-red-400' ?>"></div>
                        <span class="text-lg font-bold <?= $interface_running ? 'text-green-400' : 'text-red-400' ?>">
                            <?= $interface_running ? 'UP' : 'DOWN' ?>
                        </span>
                    </div>
                </div>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mb-1">Interface <?= $current_interface ?></h3>
            <p class="text-xs text-gray-500">Network State: <?= $network_stats['state'] ?? 'UNKNOWN' ?></p>
        </div>

        <!-- Active Peers -->
        <div class="glass-card p-4 lg:p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-green-400 text-lg lg:text-xl"></i>
                </div>
                <span class="text-2xl font-bold text-white"><?= count($peers) ?></span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mb-1">Connected Peers</h3>
            <p class="text-xs text-gray-500">Active connections</p>
        </div>

        <!-- Data Received -->
        <div class="glass-card p-4 lg:p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-yellow-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-download text-yellow-400 text-lg lg:text-xl"></i>
                </div>
                <span class="text-lg font-bold text-white"><?= formatBytes($network_stats['rx_bytes'] ?? 0) ?></span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mb-1">Data Received</h3>
            <p class="text-xs text-gray-500"><?= number_format($network_stats['rx_packets'] ?? 0) ?> packets</p>
        </div>

        <!-- Data Sent -->
        <div class="glass-card p-4 lg:p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-purple-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-upload text-purple-400 text-lg lg:text-xl"></i>
                </div>
                <span class="text-lg font-bold text-white"><?= formatBytes($network_stats['tx_bytes'] ?? 0) ?></span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mb-1">Data Sent</h3>
            <p class="text-xs text-gray-500"><?= number_format($network_stats['tx_packets'] ?? 0) ?> packets</p>
        </div>
    </div>

    <!-- Interface Details -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Interface Configuration -->
        <div class="glass-card p-4 lg:p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-white">Interface Configuration</h2>
                <!-- Interface Controls -->
                <div class="flex gap-2">
                    <form method="POST" class="inline">
                        <input type="hidden" name="interface_action" value="start">
                        <button type="submit" class="px-2 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs transition-colors" 
                                <?= $interface_running ? 'disabled' : '' ?>>
                            <i class="fas fa-play mr-1"></i>Start
                        </button>
                    </form>
                    <form method="POST" class="inline">
                        <input type="hidden" name="interface_action" value="stop">
                        <button type="submit" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs transition-colors"
                                <?= !$interface_running ? 'disabled' : '' ?>>
                            <i class="fas fa-stop mr-1"></i>Stop
                        </button>
                    </form>
                    <form method="POST" class="inline">
                        <input type="hidden" name="interface_action" value="restart">
                        <button type="submit" class="px-2 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-xs transition-colors">
                            <i class="fas fa-redo mr-1"></i>Restart
                        </button>
                    </form>
                    <form method="POST" class="inline">
                        <input type="hidden" name="interface_action" value="reload">
                        <button type="submit" class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs transition-colors">
                            <i class="fas fa-sync mr-1"></i>Reload
                        </button>
                    </form>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 bg-gray-800 rounded-lg">
                    <span class="text-sm font-medium text-gray-300">Interface Name</span>
                    <span class="text-sm text-white font-mono"><?= $current_interface ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-800 rounded-lg">
                    <span class="text-sm font-medium text-gray-300">Public Key</span>
                    <div class="flex items-center">
                        <span class="text-sm text-white font-mono mr-2"><?= substr($wg_details['public_key'], 0, 20) ?>...</span>
                        <button onclick="copyToClipboard('<?= htmlspecialchars($wg_details['public_key']) ?>')" 
                                class="text-gray-400 hover:text-white transition-colors">
                            <i class="fas fa-copy text-xs"></i>
                        </button>
                    </div>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-800 rounded-lg">
                    <span class="text-sm font-medium text-gray-300">Listen Port</span>
                    <span class="text-sm text-white font-mono"><?= $wg_details['listen_port'] ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-800 rounded-lg">
                    <span class="text-sm font-medium text-gray-300">MTU</span>
                    <span class="text-sm text-white font-mono"><?= $network_stats['mtu'] ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-800 rounded-lg">
                    <span class="text-sm font-medium text-gray-300">Server IP</span>
                    <span class="text-sm text-white font-mono"><?= SERVER_IP ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-800 rounded-lg">
                    <span class="text-sm font-medium text-gray-300">Subnet</span>
                    <span class="text-sm text-white font-mono"><?= SUBNET ?></span>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="glass-card p-4 lg:p-6">
            <h2 class="text-lg font-semibold text-white mb-4">System Information</h2>
            
            <div class="space-y-3">
                <?php if (isset($system_stats['load'])): ?>
                <div class="flex justify-between items-center p-3 bg-gray-800 rounded-lg">
                    <span class="text-sm font-medium text-gray-300">System Load</span>
                    <span class="text-sm text-white">
                        <?= number_format($system_stats['load']['1min'], 2) ?> / 
                        <?= number_format($system_stats['load']['5min'], 2) ?> / 
                        <?= number_format($system_stats['load']['15min'], 2) ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (isset($system_stats['memory'])): ?>
                <div class="p-3 bg-gray-800 rounded-lg">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-300">Memory Usage</span>
                        <span class="text-sm text-white"><?= $system_stats['memory']['percent'] ?>%</span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $system_stats['memory']['percent'] ?>%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span><?= formatBytes($system_stats['memory']['used']) ?> used</span>
                        <span><?= formatBytes($system_stats['memory']['total']) ?> total</span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center p-3 bg-gray-800 rounded-lg">
                    <span class="text-sm font-medium text-gray-300">Uptime</span>
                    <span class="text-sm text-white"><?= $system_stats['uptime'] ?? 'N/A' ?></span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-800 rounded-lg">
                    <span class="text-sm font-medium text-gray-300">Last Updated</span>
                    <span class="text-sm text-white" id="lastUpdated"><?= date('H:i:s') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Raw WireGuard Output -->
    <?php if ($interface_running && $interface_status !== 'Error'): ?>
    <div class="glass-card p-4 lg:p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-white">WireGuard Status Output</h2>
            <button onclick="toggleRawOutput()" class="px-3 py-1 bg-gray-600 hover:bg-gray-700 text-white rounded text-sm transition-colors">
                <i class="fas fa-eye mr-1"></i>Toggle View
            </button>
        </div>
        <div id="rawOutput" class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
            <pre class="text-sm text-green-400 whitespace-pre-wrap"><?= htmlspecialchars($interface_status) ?></pre>
        </div>
    </div>
    <?php endif; ?>

    <!-- Connected Peers Table -->
    <?php if (!empty($peers)): ?>
    <div class="glass-card overflow-hidden">
        <div class="px-4 lg:px-6 py-4 border-b border-gray-600">
            <h2 class="text-lg font-semibold text-white">Connected Peers</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Public Key</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Endpoint</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Allowed IPs</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Last Handshake</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Transfer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-600">
                    <?php foreach ($peers as $peer): ?>
                    <tr class="hover:bg-gray-800/50 transition-colors">
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-500 bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-blue-400 text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-white"><?= htmlspecialchars($peer['name'] ?? 'Unnamed') ?></span>
                            </div>
                        </td>
                        <td class="px-4 lg:px-6 py-4">
                            <span class="text-sm text-gray-300 font-mono" title="<?= htmlspecialchars($peer['public_key']) ?>">
                                <?= htmlspecialchars(substr($peer['public_key'], 0, 16)) ?>...
                            </span>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?= htmlspecialchars($peer['endpoint'] ?? 'N/A') ?>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?= htmlspecialchars($peer['allowed_ips'] ?? 'N/A') ?>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?= isset($peer['last_handshake']) ? date('M j, H:i:s', strtotime($peer['last_handshake'])) : 'Never' ?>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?php if (isset($peer['transfer_rx']) && isset($peer['transfer_tx'])): ?>
                                <div>↓ <?= formatBytes($peer['transfer_rx']) ?></div>
                                <div>↑ <?= formatBytes($peer['transfer_tx']) ?></div>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
let autoRefreshInterval = null;
let rawOutputVisible = true;

function changeInterface(interfaceName) {
    window.location.href = `?interface=${interfaceName}`;
}

function refreshStatus() {
    window.location.reload();
}

function toggleAutoRefresh() {
    const checkbox = document.getElementById('autoRefresh');
    if (checkbox.checked) {
        autoRefreshInterval = setInterval(refreshStatus, 30000); // 30 seconds
        console.log('Auto-refresh enabled (30s)');
    } else {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
        console.log('Auto-refresh disabled');
    }
}

function toggleRawOutput() {
    const rawOutput = document.getElementById('rawOutput');
    if (rawOutputVisible) {
        rawOutput.style.display = 'none';
        rawOutputVisible = false;
    } else {
        rawOutput.style.display = 'block';
        rawOutputVisible = true;
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        toast.textContent = 'Copied to clipboard!';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    }).catch(() => {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Copied to clipboard!');
    });
}

// Update last updated time
setInterval(() => {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    const lastUpdated = document.getElementById('lastUpdated');
    if (lastUpdated) {
        lastUpdated.textContent = timeString;
    }
}, 1000);

// Handle page visibility change (pause auto-refresh when tab is hidden)
document.addEventListener('visibilitychange', () => {
    const checkbox = document.getElementById('autoRefresh');
    if (document.hidden && autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    } else if (!document.hidden && checkbox.checked) {
        autoRefreshInterval = setInterval(refreshStatus, 30000);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>