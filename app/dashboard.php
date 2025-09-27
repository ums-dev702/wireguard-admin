<?php 
require_once __DIR__ . '/../includes/header.php'; 

// Get current interface (from URL or default)
$current_interface = $_GET['interface'] ?? WG_IFACE;

// Function to get interface configuration details
function getInterfaceConfig($interface) {
    $config = [
        'port' => 'N/A',
        'address' => 'N/A',
        'subnet' => SUBNET, // fallback
        'server_ip' => SERVER_IP // fallback
    ];
    
    // Try to get from database first
    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT * FROM interfaces WHERE name = ? AND status = "active" LIMIT 1');
        $stmt->execute([$interface]);
        $db_interface = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($db_interface) {
            $config['port'] = $db_interface['port'] ?? 'N/A';
            $config['address'] = $db_interface['address'] ?? 'N/A';
        }
    } catch (Exception $e) {
        error_log("Error fetching interface from database: " . $e->getMessage());
    }
    
    // Try to get from WireGuard configuration file
    $conf_path = "/etc/wireguard/wg_{$interface}.conf";
    if (file_exists($conf_path)) {
        $conf_content = file_get_contents($conf_path);
        
        // Extract ListenPort
        if (preg_match('/ListenPort\s*=\s*(\d+)/', $conf_content, $matches)) {
            $config['port'] = $matches[1];
        }
        
        // Extract Address
        if (preg_match('/Address\s*=\s*([^\r\n]+)/', $conf_content, $matches)) {
            $config['address'] = trim($matches[1]);
            // If it's a single IP, try to extract subnet
            if (strpos($config['address'], '/') !== false) {
                $parts = explode('/', $config['address']);
                if (count($parts) >= 2) {
                    $config['subnet'] = $parts[0] . '/' . $parts[1];
                }
            }
        }
    }
    
    // If still N/A, try to get from wg show command
    if ($config['port'] === 'N/A') {
        $wg_output = shell_exec("sudo wg show {$interface} listen-port 2>/dev/null");
        if ($wg_output && trim($wg_output) !== '') {
            $config['port'] = trim($wg_output);
        }
    }
    
    return $config;
}

// Get interface configuration
$interface_config = getInterfaceConfig($current_interface);
?>


    <!-- Dashboard Content -->
    <div class="p-4 lg:p-6">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-5 mb-6 lg:mb-8 stats-grid">
            <!-- Active Peers -->
            <div class="glass-card p-4 lg:p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-400 text-lg lg:text-xl"></i>
                    </div>
                    <span class="stat-number"><?= count($peers) ?></span>
                </div>
                <h3 class="text-sm font-medium text-gray-400 mb-1">Active Peers</h3>
                <p class="text-xs text-gray-500">VPN connections</p>
            </div>

            <!-- System Load -->
            <div class="glass-card p-4 lg:p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tachometer-alt text-green-400 text-lg lg:text-xl"></i>
                    </div>
                    <span class="stat-number"><?= number_format($systemStats['load']['1min'], 2) ?></span>
                </div>
                <h3 class="text-sm font-medium text-gray-400 mb-1">System Load</h3>
                <p class="text-xs text-gray-500">1 minute average</p>
            </div>

            <!-- Memory Usage -->
            <div class="glass-card p-4 lg:p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-yellow-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-memory text-yellow-400 text-lg lg:text-xl"></i>
                    </div>
                    <span class="stat-number">
                        <?php if (isset($systemStats['memory']) && isset($systemStats['memory']['percent'])): ?>
                            <?= $systemStats['memory']['percent'] ?>%
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </span>
                </div>
                <h3 class="text-sm font-medium text-gray-400 mb-1">Memory Usage</h3>
                <div class="progress-bar">
                    <?php if (isset($systemStats['memory']) && isset($systemStats['memory']['percent'])): ?>
                        <div class="progress-fill bg-yellow-400" style="width: <?= $systemStats['memory']['percent'] ?>%"></div>
                    <?php else: ?>
                        <div class="progress-fill bg-gray-600" style="width: 100%"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Disk Usage -->
            <div class="glass-card p-4 lg:p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-purple-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-hdd text-purple-400 text-lg lg:text-xl"></i>
                    </div>
                    <span class="stat-number"><?= $systemStats['disk']['percent'] ?>%</span>
                </div>
                <h3 class="text-sm font-medium text-gray-400 mb-1">Disk Usage</h3>
                <div class="progress-bar">
                    <div class="progress-fill bg-purple-400" style="width: <?= $systemStats['disk']['percent'] ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Recent Peers and Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-5 peer-grid">
            <!-- WireGuard Overview & Controls -->
            <div class="lg:col-span-2 glass-card p-4 lg:p-5">
                <div class="flex items-center justify-between mb-4 lg:mb-6">
                    <h2 class="text-lg font-bold text-white">
                        <i class="fas fa-shield-alt text-blue-400 mr-2"></i>
                        WireGuard Interfaces Overview
                    </h2>
                    <div class="flex items-center gap-3">
                        <select id="interfaceSelector" onchange="switchInterface(this.value)" class="px-3 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm">
                            <?php 
                            $available_interfaces = get_available_interfaces();
                            foreach ($available_interfaces as $iface): 
                            ?>
                            <option value="<?= $iface ?>" <?= $iface === $current_interface ? 'selected' : '' ?>>
                                <?= htmlspecialchars($iface) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-2 <?= $isRunning ? 'bg-green-400' : 'bg-red-400' ?>"></div>
                            <span class="text-sm <?= $isRunning ? 'text-green-400' : 'text-red-400' ?>">
                                <?= $isRunning ? 'Running' : 'Stopped' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Interface Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                    <?php
                    try {
                        ensure_interfaces_table();
                        $db = get_db();
                        $interface_stats = $db->query('SELECT COUNT(*) as total, 
                                                     SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active
                                                     FROM interfaces')->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $interface_stats = ['total' => 0, 'active' => 0];
                    }
                    ?>
                    
                    <div class="bg-gray-800 bg-opacity-50 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-white"><?= $interface_stats['total'] ?></div>
                                <div class="text-xs text-gray-400">Total Interfaces</div>
                            </div>
                            <i class="fas fa-network-wired text-blue-400"></i>
                        </div>
                    </div>
                    
                    <div class="bg-gray-800 bg-opacity-50 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-green-400"><?= $interface_stats['active'] ?></div>
                                <div class="text-xs text-gray-400">Active Interfaces</div>
                            </div>
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                    </div>
                    
                    <div class="bg-gray-800 bg-opacity-50 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-white"><?= count($peers) ?></div>
                                <div class="text-xs text-gray-400">Connected Peers</div>
                            </div>
                            <i class="fas fa-users text-purple-400"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="glass-card p-4 lg:p-5">
                <h2 class="text-lg font-bold text-white mb-4 lg:mb-6">
                    <i class="fas fa-bolt text-yellow-400 mr-2"></i>
                    Quick Actions
                </h2>

                <div class="space-y-3">
                    <a href="create_interface" class="block w-full bg-white bg-opacity-5 hover:bg-opacity-10 border border-white border-opacity-5 rounded-xl p-3 lg:p-4 transition-all">
                        <div class="flex items-center">
                            <div class="w-8 h-8 lg:w-10 lg:h-10 bg-green-500 bg-opacity-10 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-plus text-green-400"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-white text-sm lg:text-base">Create an Interface</h3>
                                <p class="text-xs lg:text-sm text-gray-400">Create a new WG interface</p>
                            </div>
                        </div>
                    </a>

                    <a href="wg_peers" class="block w-full bg-white bg-opacity-5 hover:bg-opacity-10 border border-white border-opacity-5 rounded-xl p-3 lg:p-4 transition-all">
                        <div class="flex items-center">
                            <div class="w-8 h-8 lg:w-10 lg:h-10 bg-blue-500 bg-opacity-10 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-user-friends text-blue-400"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-white text-sm lg:text-base">Manage Peers</h3>
                                <p class="text-xs lg:text-sm text-gray-400">Add and manage WireGuard peers</p>
                            </div>
                        </div>
                    </a>

                    <button onclick="refresh()" class="block w-full bg-white bg-opacity-5 hover:bg-opacity-10 border border-white border-opacity-5 rounded-xl p-3 lg:p-4 transition-all text-left">
                        <div class="flex items-center">
                            <div class="w-8 h-8 lg:w-10 lg:h-10 bg-purple-500 bg-opacity-10 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-sync text-purple-400"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-white text-sm lg:text-base">Refresh Dashboard</h3>
                                <p class="text-xs lg:text-sm text-gray-400">Update system information</p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        
    </div>


<script>
    // Function to switch between interfaces
    function switchInterface(interfaceName) {
        window.location.href = `dashboard?interface=${interfaceName}`;
    }

    // Function to refresh system stats via AJAX
    function refresh() {
        location.reload();
    }

    // Function to control WireGuard interface
    function controlInterface(action) {
        if (action === 'stop' && !confirm('Are you sure you want to stop the WireGuard interface?')) {
            return;
        }

        // Show loading state
        const buttons = document.querySelectorAll('button[onclick^="controlInterface"]');
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.6';
        });

        // Get current interface from selector
        const currentInterface = document.getElementById('interfaceSelector').value;

        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `wg_status?interface=${currentInterface}`;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'interface_action';
        actionInput.value = action;
        
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }

    // Function to refresh stats without full page reload
    function refreshStats() {
        // Simple page reload for now - can be enhanced with AJAX later
        location.reload();
    }

    // Auto-update the "Last Updated" time
    function updateLastUpdated() {
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        const lastUpdated = document.getElementById('lastUpdated');
        if (lastUpdated) {
            lastUpdated.textContent = timeString;
        }
    }

    // Update time every second
    setInterval(updateLastUpdated, 1000);

    // Auto-refresh dashboard every 5 minutes
    setTimeout(() => {
        if (confirm('Auto-refresh dashboard data?')) {
            refreshStats();
        }
    }, 300000); // 5 minutes

    // Add visual feedback for button interactions
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('button[onclick]');
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 100);
            });
        });
    });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>