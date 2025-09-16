<?php require_once __DIR__ . '/includes/header.php'; ?>
<!-- Main Content -->
<div class="main-content ml-0 lg:ml-64 min-h-screen">
    <!-- Top Bar -->
    <div class="top-bar p-4">
        <div class="flex items-center justify-between top-bar-content">
            <div>
                <h1 class="text-2xl font-bold text-white">Dashboard</h1>
                <p class="text-gray-400">Welcome back, <?= htmlspecialchars($currentUser['username']) ?>!</p>
            </div>
            <div class="flex items-center space-x-4 status-indicator">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full <?= $isRunning ? 'bg-green-400 animate-pulse' : 'bg-red-400' ?> mr-2"></div>
                    <span class="text-sm font-medium <?= $isRunning ? 'text-green-400' : 'text-red-400' ?>">
                        WireGuard <?= $isRunning ? 'Running' : 'Stopped' ?>
                    </span>
                </div>
                <button class="w-10 h-10 rounded-lg flex items-center justify-center bg-white bg-opacity-5 hover:bg-opacity-10 transition-all">
                    <i class="fas fa-bell text-gray-400"></i>
                </button>
            </div>
        </div>
    </div>

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
            <!-- Recent Peers -->
            <div class="lg:col-span-2 glass-card p-4 lg:p-5">
                <div class="flex items-center justify-between mb-4 lg:mb-6">
                    <h2 class="text-lg font-bold text-white">
                        <i class="fas fa-users text-blue-400 mr-2"></i>
                        Recent Peers
                    </h2>
                    <a href="wg-peers.php" class="text-sm text-blue-400 hover:text-blue-300 font-medium flex items-center">
                        View All <i class="fas fa-arrow-right ml-1 text-xs"></i>
                    </a>
                </div>

                <?php if (empty($peers)): ?>
                    <div class="text-center py-6 lg:py-8">
                        <i class="fas fa-users text-gray-600 text-3xl lg:text-4xl mb-3 lg:mb-4"></i>
                        <p class="text-gray-400 mb-3 lg:mb-4">No VPN peers configured yet</p>
                        <a href="wg-peers.php" class="floating-btn inline-flex items-center px-4 py-2 rounded-lg text-white text-sm lg:text-base">
                            <i class="fas fa-plus mr-2"></i>
                            Add Your First Peer
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-gray-400 text-sm">
                                    <th class="pb-3 px-2 lg:px-4 font-medium">Name</th>
                                    <th class="pb-3 px-2 lg:px-4 font-medium">IP Address</th>
                                    <th class="pb-3 px-2 lg:px-4 font-medium">Last Seen</th>
                                    <th class="pb-3 px-2 lg:px-4 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($peers, 0, 5) as $peer): ?>
                                    <tr class="table-row">
                                        <td class="py-3 px-2 lg:px-4">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-green-500 bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-laptop text-green-400 text-sm"></i>
                                                </div>
                                                <span class="font-medium text-white text-sm lg:text-base"><?= htmlspecialchars($peer['name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-2 lg:px-4 text-gray-400 text-sm"><?= htmlspecialchars($peer['allowed_ips']) ?></td>
                                        <td class="py-3 px-2 lg:px-4 text-gray-400 text-sm">
                                            <?= $peer['last_handshake'] ? date('M j, H:i', strtotime($peer['last_handshake'])) : 'Never' ?>
                                        </td>
                                        <td class="py-3 px-2 lg:px-4">
                                            <?php
                                            $isOnline = $peer['last_handshake'] && (time() - strtotime($peer['last_handshake'])) < 300;
                                            ?>
                                            <span class="status-badge <?= $isOnline ? 'bg-green-900 bg-opacity-20 text-green-400' : 'bg-gray-700 text-gray-400' ?>">
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
            <div class="glass-card p-4 lg:p-5">
                <h2 class="text-lg font-bold text-white mb-4 lg:mb-6">
                    <i class="fas fa-bolt text-yellow-400 mr-2"></i>
                    Quick Actions
                </h2>

                <div class="space-y-3">
                    <a href="wg-peers.php?action=add" class="block w-full bg-white bg-opacity-5 hover:bg-opacity-10 border border-white border-opacity-5 rounded-xl p-3 lg:p-4 transition-all">
                        <div class="flex items-center">
                            <div class="w-8 h-8 lg:w-10 lg:h-10 bg-green-500 bg-opacity-10 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-plus text-green-400"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-white text-sm lg:text-base">Add New Peer</h3>
                                <p class="text-xs lg:text-sm text-gray-400">Create a new VPN connection</p>
                            </div>
                        </div>
                    </a>

                    <a href="port-forwarding.php" class="block w-full bg-white bg-opacity-5 hover:bg-opacity-10 border border-white border-opacity-5 rounded-xl p-3 lg:p-4 transition-all">
                        <div class="flex items-center">
                            <div class="w-8 h-8 lg:w-10 lg:h-10 bg-blue-500 bg-opacity-10 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-network-wired text-blue-400"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-white text-sm lg:text-base">Port Forwarding</h3>
                                <p class="text-xs lg:text-sm text-gray-400">Manage network routes</p>
                            </div>
                        </div>
                    </a>

                    <button onclick="refreshStats()" class="block w-full bg-white bg-opacity-5 hover:bg-opacity-10 border border-white border-opacity-5 rounded-xl p-3 lg:p-4 transition-all text-left">
                        <div class="flex items-center">
                            <div class="w-8 h-8 lg:w-10 lg:h-10 bg-purple-500 bg-opacity-10 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-sync text-purple-400"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-white text-sm lg:text-base">Refresh Stats</h3>
                                <p class="text-xs lg:text-sm text-gray-400">Update system information</p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>