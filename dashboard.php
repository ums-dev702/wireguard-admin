<?php
require_once __DIR__ . '/config.php';

try {
    $db = new \WireGuardAdmin\Database();
    $auth = new \WireGuardAdmin\Auth($db, SESSION_TIMEOUT);
    $wg = new \WireGuardAdmin\WireGuard($db, WG_IFACE, WG_CONF_PATH);
    
    // Check authentication
    $auth->requireAuth('/login.php');
    
    $currentUser = $auth->getCurrentUser();
    
    // Update peer statistics
    $wg->updatePeerStats();
    
    // Get system information
    $wgStatus = $wg->getStatus();
    $isRunning = $wg->isRunning();
    $peers = $wg->getPeers();
    $systemStats = $wg->getSystemStats();
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }
        
        .status-indicator {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.7) 100%);
            backdrop-filter: blur(10px);
        }
        
        .nav-link {
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .table-row-hover {
            transition: all 0.2s ease;
        }
        
        .table-row-hover:hover {
            background: rgba(16, 185, 129, 0.05);
            transform: scale(1.01);
        }
        
        .loading-overlay {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
        }
        
        .floating-action {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
        }
        
        .floating-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.4);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <!-- Navigation Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-xl z-30" id="sidebar">
        <div class="flex flex-col h-full">
            <!-- Logo -->
            <div class="flex items-center justify-center p-6 border-b">
                <i class="fas fa-shield-alt text-2xl text-green-600 mr-3"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?= APP_NAME ?></h1>
                    <p class="text-xs text-gray-600">v<?= APP_VERSION ?></p>
                </div>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 space-y-2">
                <a href="dashboard.php" class="nav-link active flex items-center p-3 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="wg-peers.php" class="nav-link flex items-center p-3 rounded-lg">
                    <i class="fas fa-users mr-3"></i>
                    VPN Peers
                </a>
                <a href="port-forwarding.php" class="nav-link flex items-center p-3 rounded-lg">
                    <i class="fas fa-network-wired mr-3"></i>
                    Port Forwarding
                </a>
                <a href="settings.php" class="nav-link flex items-center p-3 rounded-lg">
                    <i class="fas fa-cog mr-3"></i>
                    Settings
                </a>
                <a href="logs.php" class="nav-link flex items-center p-3 rounded-lg">
                    <i class="fas fa-file-alt mr-3"></i>
                    Audit Logs
                </a>
            </nav>
            
            <!-- User Menu -->
            <div class="p-4 border-t">
                <div class="flex items-center mb-3">
                    <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($currentUser['username']) ?></p>
                        <p class="text-xs text-gray-600"><?= htmlspecialchars($currentUser['role']) ?></p>
                    </div>
                </div>
                <a href="logout.php" class="nav-link flex items-center p-2 rounded-lg text-red-600">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ml-64 min-h-screen">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm border-b p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                    <p class="text-gray-600">Welcome back, <?= htmlspecialchars($currentUser['username']) ?>!</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full <?= $isRunning ? 'bg-green-400 status-indicator' : 'bg-red-400' ?> mr-2"></div>
                        <span class="text-sm font-medium <?= $isRunning ? 'text-green-600' : 'text-red-600' ?>">
                            WireGuard <?= $isRunning ? 'Running' : 'Stopped' ?>
                        </span>
                    </div>
                    <button class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors">
                        <i class="fas fa-bell text-gray-600"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="p-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Active Peers -->
                <div class="stat-card rounded-xl p-6 card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <span class="text-2xl font-bold text-gray-800"><?= count($peers) ?></span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-600 mb-1">Active Peers</h3>
                    <p class="text-xs text-gray-500">VPN connections</p>
                </div>

                <!-- System Load -->
                <div class="stat-card rounded-xl p-6 card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-tachometer-alt text-green-600 text-xl"></i>
                        </div>
                        <span class="text-2xl font-bold text-gray-800"><?= number_format($systemStats['load']['1min'], 2) ?></span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-600 mb-1">System Load</h3>
                    <p class="text-xs text-gray-500">1 minute average</p>
                </div>

                <!-- Memory Usage -->
                <div class="stat-card rounded-xl p-6 card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-memory text-yellow-600 text-xl"></i>
                        </div>
                        <span class="text-2xl font-bold text-gray-800"><?= $systemStats['memory']['percent'] ?>%</span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-600 mb-1">Memory Usage</h3>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full progress-bar" style="width: <?= $systemStats['memory']['percent'] ?>%"></div>
                    </div>
                </div>

                <!-- Disk Usage -->
                <div class="stat-card rounded-xl p-6 card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-hdd text-purple-600 text-xl"></i>
                        </div>
                        <span class="text-2xl font-bold text-gray-800"><?= $systemStats['disk']['percent'] ?>%</span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-600 mb-1">Disk Usage</h3>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-600 h-2 rounded-full progress-bar" style="width: <?= $systemStats['disk']['percent'] ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Recent Peers and Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Peers -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-users text-blue-600 mr-2"></i>
                            Recent Peers
                        </h2>
                        <a href="wg-peers.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($peers)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-users text-gray-300 text-4xl mb-4"></i>
                            <p class="text-gray-500 mb-4">No VPN peers configured yet</p>
                            <a href="wg-peers.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Add Your First Peer
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-3 px-4 font-medium text-gray-600">Name</th>
                                        <th class="text-left py-3 px-4 font-medium text-gray-600">IP Address</th>
                                        <th class="text-left py-3 px-4 font-medium text-gray-600">Last Seen</th>
                                        <th class="text-left py-3 px-4 font-medium text-gray-600">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($peers, 0, 5) as $peer): ?>
                                        <tr class="table-row-hover">
                                            <td class="py-3 px-4">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                                        <i class="fas fa-laptop text-green-600 text-sm"></i>
                                                    </div>
                                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($peer['name']) ?></span>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($peer['allowed_ips']) ?></td>
                                            <td class="py-3 px-4 text-gray-600">
                                                <?= $peer['last_handshake'] ? date('M j, H:i', strtotime($peer['last_handshake'])) : 'Never' ?>
                                            </td>
                                            <td class="py-3 px-4">
                                                <?php 
                                                $isOnline = $peer['last_handshake'] && (time() - strtotime($peer['last_handshake'])) < 300;
                                                ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $isOnline ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                                    <div class="w-2 h-2 rounded-full <?= $isOnline ? 'bg-green-400' : 'bg-gray-400' ?> mr-1"></div>
                                                    <?= $isOnline ? 'Online' : 'Offline' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                        Quick Actions
                    </h2>
                    
                    <div class="space-y-4">
                        <a href="wg-peers.php?action=add" class="block w-full bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg p-4 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-plus text-green-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-800">Add New Peer</h3>
                                    <p class="text-sm text-gray-600">Create a new VPN connection</p>
                                </div>
                            </div>
                        </a>
                        
                        <a href="port-forwarding.php" class="block w-full bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg p-4 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-network-wired text-blue-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-800">Port Forwarding</h3>
                                    <p class="text-sm text-gray-600">Manage network routes</p>
                                </div>
                            </div>
                        </a>
                        
                        <button onclick="refreshStats()" class="block w-full bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg p-4 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-sync text-purple-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-800">Refresh Stats</h3>
                                    <p class="text-sm text-gray-600">Update system information</p>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 loading-overlay hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 shadow-xl">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin text-green-600 text-2xl mr-4"></i>
                <span class="text-lg font-medium text-gray-800">Updating...</span>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh stats every 30 seconds
        setInterval(refreshStats, 30000);
        
        function refreshStats() {
            const overlay = document.getElementById('loading-overlay');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            
            // Simulate API call - replace with actual AJAX call
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
        
        // Initialize tooltips and other interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add any initialization code here
            console.log('Dashboard loaded successfully');
        });
    </script>
</body>
</html>
            <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                <div class="flex items-center mb-4">
                    <svg class="w-6 h-6 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-700">Disk</h3>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?= $diskpercent ?>%</p>
                <p class="text-sm text-gray-600"><?= format_bytes($diskused) ?> / <?= format_bytes($disktotal) ?></p>
                <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                    <div class="bg-yellow-600 h-2.5 rounded-full progress-bar" style="width: <?= $diskpercent ?>%"></div>
                </div>
            </div>

            <!-- WireGuard Status -->
            <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                <div class="flex items-center mb-4">
                    <svg class="w-6 h-6 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2-2 4-2 4m-4-2H6a2 2 0 01-2-2V7a2 2 0 012-2h4m4 2h4a2 2 0 012 2v6a2 2 0 01-2 2h-4m-4 0H6m6-6v6"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-700">WireGuard</h3>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?= count($peers) ?> Peers</p>
                <p class="text-sm text-gray-600"><?= count($port_rules) ?> Port Rules</p>
                <p class="text-sm text-gray-600">Status: <span class="text-green-600 font-semibold">Running</span></p>
            </div>
        </div>

        <!-- VPN Peers and Port Rules -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- VPN Peers -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-700">VPN Peers</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-gray-600">
                                <th class="py-2 px-4">Public Key</th>
                                <th class="py-2 px-4">Allowed IPs</th>
                                <th class="py-2 px-4">Transfer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peers as $peer): ?>
                            <tr class="border-t table-row-hover">
                                <td class="py-2 px-4"><?= htmlspecialchars(substr($peer['public_key'], 0, 16)) ?>...</td>
                                <td class="py-2 px-4"><?= htmlspecialchars($peer['allowed_ips']) ?></td>
                                <td class="py-2 px-4"><?= htmlspecialchars($peer['transfer']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="/wg-peers.php" class="mt-4 inline-block bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">Manage Peers</a>
            </div>

            <!-- Port Forwarding Rules -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m0-2v-2m0-2V7m6 10v-2m0-2v-2m0-2V7m-3 14v-14"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-700">Port Forwarding Rules</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-gray-600">
                                <th class="py-2 px-4">External Port</th>
                                <th class="py-2 px-4">Internal IP</th>
                                <th class="py-2 px-4">Internal Port</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($port_rules as $rule): ?>
                            <tr class="border-t table-row-hover">
                                <td class="py-2 px-4"><?= htmlspecialchars($rule['ext_port']) ?></td>
                                <td class="py-2 px-4"><?= htmlspecialchars($rule['int_ip']) ?></td>
                                <td class="py-2 px-4"><?= htmlspecialchars($rule['int_port']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="/port-forwarding.php" class="mt-4 inline-block bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">Manage Rules</a>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>