<?php
require_once __DIR__ . '/../includes/header.php';

function dashboard_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function getInterfaceConfig($interface): array
{
    $config = [
        'port' => 'N/A',
        'subnet' => 'N/A',
        'server_ip' => defined('SERVER_IP') ? SERVER_IP : 'N/A'
    ];

    if ($interface === '') {
        return $config;
    }

    try {
        $db = get_db();
        $cleanInterface = preg_replace('/^wg_/', '', $interface);
        $stmt = $db->prepare('SELECT * FROM interfaces WHERE name = ? AND status = "active" LIMIT 1');
        $stmt->execute([$cleanInterface]);
        $dbInterface = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dbInterface) {
            $config['port'] = $dbInterface['port'] ?? 'N/A';
            $config['subnet'] = $dbInterface['address'] ?? 'N/A';
        }
    } catch (Exception $e) {
        error_log("Error fetching interface from database: " . $e->getMessage());
    }

    $configPath = "/etc/wireguard/{$interface}.conf";
    if (file_exists($configPath)) {
        $configContent = file_get_contents($configPath);
        if (preg_match('/ListenPort\s*=\s*(\d+)/', $configContent, $matches)) {
            $config['port'] = $matches[1];
        }
    }

    if ($config['port'] === 'N/A') {
        $wgOutput = shell_exec("sudo wg show " . escapeshellarg($interface) . " listen-port 2>/dev/null");
        if ($wgOutput && trim($wgOutput) !== '') {
            $config['port'] = trim($wgOutput);
        }
    }

    return $config;
}

$availableInterfaces = get_available_interfaces();
$currentInterface = $_GET['interface'] ?? ($availableInterfaces[0] ?? '');

if ($currentInterface === '' && !empty($availableInterfaces)) {
    $currentInterface = $availableInterfaces[0];
}

$interfaceConfig = getInterfaceConfig($currentInterface);
$memoryPercent = $systemStats['memory']['percent'] ?? null;
$diskPercent = $systemStats['disk']['percent'] ?? 0;
$loadOneMinute = $systemStats['load']['1min'] ?? 0;
$networkRx = $systemStats['network']['rx_bytes'] ?? 0;
$networkTx = $systemStats['network']['tx_bytes'] ?? 0;

$interfaceStats = ['total' => 0, 'active' => 0];
try {
    ensure_interfaces_table();
    $statsDb = get_db();
    $interfaceStats = $statsDb->query('SELECT COUNT(*) as total, SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active FROM interfaces')->fetch(PDO::FETCH_ASSOC);
    $interfaceStats['total'] = (int) ($interfaceStats['total'] ?? 0);
    $interfaceStats['active'] = (int) ($interfaceStats['active'] ?? 0);
} catch (Exception $e) {
    error_log("Dashboard interface stats error: " . $e->getMessage());
}

$recentPeers = array_filter($peers, function ($peer) {
    $timestamp = $peer['updated_at'] ?? $peer['last_handshake'] ?? null;
    return $timestamp && strtotime($timestamp) > (time() - 3600);
});

$totalRx = 0;
$totalTx = 0;
foreach ($peers as $peer) {
    $totalRx += (int) ($peer['rx_bytes'] ?? $peer['transfer_rx'] ?? 0);
    $totalTx += (int) ($peer['tx_bytes'] ?? $peer['transfer_tx'] ?? 0);
}

$totalTraffic = $totalRx + $totalTx;
$healthLabel = $isRunning ? 'Protected' : 'Needs Attention';
$healthClass = $isRunning ? 'text-green-300 bg-green-500 bg-opacity-10 border-green-400 border-opacity-20' : 'text-red-300 bg-red-500 bg-opacity-10 border-red-400 border-opacity-20';
?>

<style>
    .dashboard-hero {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        border: 1px solid rgba(16, 185, 129, 0.18);
        background:
            radial-gradient(circle at 16% 18%, rgba(20, 241, 164, 0.26), transparent 32%),
            radial-gradient(circle at 86% 18%, rgba(56, 189, 248, 0.16), transparent 30%),
            linear-gradient(135deg, rgba(15, 23, 42, 0.88), rgba(2, 6, 23, 0.76));
        box-shadow: 0 26px 90px rgba(0, 0, 0, 0.32);
    }

    .dashboard-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(rgba(16, 185, 129, 0.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(16, 185, 129, 0.045) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: linear-gradient(90deg, black, transparent);
        pointer-events: none;
    }

    .vpn-map {
        position: relative;
        min-height: 230px;
    }

    .vpn-map::before,
    .vpn-map::after {
        content: "";
        position: absolute;
        left: 12%;
        right: 12%;
        top: 50%;
        height: 2px;
        background: linear-gradient(90deg, transparent, #14f1a4, transparent);
        filter: drop-shadow(0 0 12px rgba(20, 241, 164, 0.6));
        animation: dashPulse 2.8s ease-in-out infinite;
    }

    .vpn-map::after {
        transform: rotate(-20deg);
        opacity: 0.42;
        animation-delay: 0.5s;
    }

    .map-node {
        position: absolute;
        display: grid;
        place-items: center;
        border-radius: 24px;
        border: 1px solid rgba(16, 185, 129, 0.32);
        background: rgba(2, 6, 23, 0.86);
        color: #14f1a4;
        box-shadow: 0 0 34px rgba(16, 185, 129, 0.14);
        z-index: 1;
    }

    .map-node.server {
        width: 58px;
        height: 58px;
        left: 4%;
        top: 42%;
    }

    .map-node.lock {
        width: 86px;
        height: 86px;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        color: #fff;
        background: linear-gradient(135deg, #10b981, #047857);
        animation: lockFloat 3s ease-in-out infinite;
    }

    .map-node.peer {
        width: 58px;
        height: 58px;
        right: 4%;
        top: 42%;
    }

    @keyframes dashPulse {
        0%, 100% {
            opacity: 0.22;
        }
        50% {
            opacity: 1;
        }
    }

    @keyframes lockFloat {
        0%, 100% {
            transform: translate(-50%, -50%);
        }
        50% {
            transform: translate(-50%, -60%);
        }
    }

    .metric-card {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(148, 163, 184, 0.16);
        background: rgba(15, 23, 42, 0.68);
        backdrop-filter: blur(18px);
    }

    .metric-card::after {
        content: "";
        position: absolute;
        inset: auto -30% -45% -30%;
        height: 90px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.18), transparent 70%);
        pointer-events: none;
    }

    .action-tile {
        border: 1px solid rgba(148, 163, 184, 0.14);
        background: rgba(255, 255, 255, 0.045);
        border-radius: 20px;
    }

    .action-tile:hover {
        border-color: rgba(16, 185, 129, 0.3);
        background: rgba(16, 185, 129, 0.075);
        transform: translateY(-2px);
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.72rem 0;
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    }

    .detail-row:last-child {
        border-bottom: 0;
    }
</style>

<div class="p-4 lg:p-6 space-y-6">
    <section class="dashboard-hero p-5 lg:p-7">
        <div class="relative z-10 grid grid-cols-1 xl:grid-cols-5 gap-6 items-center">
            <div class="xl:col-span-3">
                <div class="inline-flex items-center px-3 py-1 rounded-full border <?= $healthClass ?> text-sm font-bold mb-5">
                    <span class="w-2 h-2 rounded-full <?= $isRunning ? 'bg-green-400 animate-pulse' : 'bg-red-400' ?> mr-2"></span>
                    VPN <?= $healthLabel ?>
                </div>
                <h2 class="text-3xl lg:text-5xl font-black text-white leading-tight mb-4">
                    Managed WireGuard VPN Control Center
                </h2>
                <p class="text-gray-300 text-base lg:text-lg max-w-3xl">
                    Monitor interfaces, peers, traffic, and server health from one clean admin dashboard.
                </p>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-7">
                    <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">Interfaces</p>
                        <p class="text-2xl font-black text-white mt-1"><?= (int) $interfaceStats['active'] ?>/<?= (int) $interfaceStats['total'] ?></p>
                    </div>
                    <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">Peers</p>
                        <p class="text-2xl font-black text-white mt-1"><?= count($peers) ?></p>
                    </div>
                    <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">Traffic</p>
                        <p class="text-2xl font-black text-white mt-1"><?= dashboard_escape($wg->formatBytes($totalTraffic)) ?></p>
                    </div>
                    <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">Server</p>
                        <p class="text-sm font-black text-green-300 mt-2 truncate"><?= dashboard_escape($interfaceConfig['server_ip']) ?></p>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-2 vpn-map">
                <div class="map-node server"><i class="fas fa-server text-xl"></i></div>
                <div class="map-node lock"><i class="fas fa-lock text-3xl"></i></div>
                <div class="map-node peer"><i class="fas fa-laptop text-xl"></i></div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 stats-grid">
        <div class="metric-card p-5">
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <p class="text-sm text-gray-400 font-bold">Active Peers</p>
                    <p class="stat-number mt-2"><?= count($peers) ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?= count($recentPeers) ?> active in the last hour</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-500 bg-opacity-10 flex items-center justify-center">
                    <i class="fas fa-users text-blue-300"></i>
                </div>
            </div>
        </div>

        <div class="metric-card p-5">
            <div class="flex items-start justify-between relative z-10 mb-4">
                <div>
                    <p class="text-sm text-gray-400 font-bold">System Load</p>
                    <p class="stat-number mt-2"><?= number_format((float) $loadOneMinute, 2) ?></p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-green-500 bg-opacity-10 flex items-center justify-center">
                    <i class="fas fa-microchip text-green-300"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500">1 minute average</p>
        </div>

        <div class="metric-card p-5">
            <div class="flex items-start justify-between relative z-10 mb-4">
                <div>
                    <p class="text-sm text-gray-400 font-bold">Memory Usage</p>
                    <p class="stat-number mt-2"><?= $memoryPercent !== null ? dashboard_escape($memoryPercent) . '%' : 'N/A' ?></p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-yellow-500 bg-opacity-10 flex items-center justify-center">
                    <i class="fas fa-memory text-yellow-300"></i>
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill bg-yellow-400" style="width: <?= $memoryPercent !== null ? dashboard_escape($memoryPercent) : 0 ?>%"></div>
            </div>
        </div>

        <div class="metric-card p-5">
            <div class="flex items-start justify-between relative z-10 mb-4">
                <div>
                    <p class="text-sm text-gray-400 font-bold">Disk Usage</p>
                    <p class="stat-number mt-2"><?= dashboard_escape($diskPercent) ?>%</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-purple-500 bg-opacity-10 flex items-center justify-center">
                    <i class="fas fa-hdd text-purple-300"></i>
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill bg-purple-400" style="width: <?= dashboard_escape($diskPercent) ?>%"></div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-3 gap-5 peer-grid">
        <div class="xl:col-span-2 glass-card p-5 lg:p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                <div>
                    <p class="text-sm uppercase tracking-widest text-green-300 font-bold">Interface Manager</p>
                    <h2 class="text-2xl font-black text-white mt-1">WireGuard Overview</h2>
                    <p class="text-gray-400 mt-1">Select an interface and control its service state.</p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <select id="interfaceSelector" onchange="switchInterface(this.value)" class="px-4 py-3 text-sm min-w-48">
                        <?php if (empty($availableInterfaces)): ?>
                            <option value="">No interfaces found</option>
                        <?php else: ?>
                            <?php foreach ($availableInterfaces as $iface): ?>
                                <option value="<?= dashboard_escape($iface) ?>" <?= $iface === $currentInterface ? 'selected' : '' ?>>
                                    <?= dashboard_escape($iface) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <span class="status-badge <?= $isRunning ? 'text-green-300' : 'text-red-300' ?>">
                        <span class="w-2 h-2 rounded-full <?= $isRunning ? 'bg-green-400 animate-pulse' : 'bg-red-400' ?>"></span>
                        <?= $isRunning ? 'Running' : 'Stopped' ?>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
                <div class="rounded-3xl bg-white bg-opacity-5 border border-white border-opacity-10 p-5">
                    <h3 class="text-lg font-bold text-white mb-4">Current Interface</h3>
                    <div class="space-y-1">
                        <div class="detail-row">
                            <span class="text-gray-400">Interface</span>
                            <span class="font-mono text-white"><?= dashboard_escape($currentInterface ?: 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="text-gray-400">Listen Port</span>
                            <span class="font-mono text-white"><?= dashboard_escape($interfaceConfig['port']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="text-gray-400">Subnet</span>
                            <span class="font-mono text-white"><?= dashboard_escape($interfaceConfig['subnet']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="text-gray-400">Endpoint</span>
                            <span class="font-mono text-white truncate"><?= dashboard_escape($interfaceConfig['server_ip']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl bg-white bg-opacity-5 border border-white border-opacity-10 p-5">
                    <h3 class="text-lg font-bold text-white mb-4">Traffic Summary</h3>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="rounded-2xl bg-gray-900 bg-opacity-50 p-4">
                            <p class="text-xs text-gray-500 font-bold uppercase">Received</p>
                            <p class="text-lg font-black text-blue-300 mt-1"><?= dashboard_escape($wg->formatBytes($totalRx ?: $networkRx)) ?></p>
                        </div>
                        <div class="rounded-2xl bg-gray-900 bg-opacity-50 p-4">
                            <p class="text-xs text-gray-500 font-bold uppercase">Sent</p>
                            <p class="text-lg font-black text-green-300 mt-1"><?= dashboard_escape($wg->formatBytes($totalTx ?: $networkTx)) ?></p>
                        </div>
                    </div>
                    <div class="detail-row">
                        <span class="text-gray-400">Recent peers</span>
                        <span class="text-white font-bold"><?= count($recentPeers) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="text-gray-400">Last updated</span>
                        <span class="text-white font-mono" id="lastUpdated"><?= date('H:i:s') ?></span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-5 border-t border-gray-700 border-opacity-50">
                <button onclick="controlInterface('start')"
                    class="px-4 py-2 text-white font-bold <?= $isRunning ? 'opacity-50 cursor-not-allowed' : '' ?>"
                    <?= $isRunning ? 'disabled' : '' ?>>
                    <i class="fas fa-play mr-2"></i>Start
                </button>
                <button onclick="controlInterface('stop')"
                    class="px-4 py-2 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-bold <?= !$isRunning ? 'opacity-50 cursor-not-allowed' : '' ?>"
                    <?= !$isRunning ? 'disabled' : '' ?>>
                    <i class="fas fa-stop mr-2"></i>Stop
                </button>
                <button onclick="controlInterface('restart')" class="px-4 py-2 rounded-2xl bg-yellow-600 hover:bg-yellow-700 text-white font-bold">
                    <i class="fas fa-redo mr-2"></i>Restart
                </button>
                <button onclick="refreshStats()" class="px-4 py-2 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-bold">
                    <i class="fas fa-sync mr-2"></i>Refresh
                </button>
                <a href="wg_status?interface=<?= dashboard_escape($currentInterface) ?>" class="px-4 py-2 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-bold">
                    <i class="fas fa-chart-line mr-2"></i>Details
                </a>
            </div>
        </div>

        <aside class="glass-card p-5 lg:p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-sm uppercase tracking-widest text-yellow-300 font-bold">Quick Actions</p>
                    <h2 class="text-2xl font-black text-white mt-1">Manage VPN</h2>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-yellow-500 bg-opacity-10 flex items-center justify-center">
                    <i class="fas fa-bolt text-yellow-300"></i>
                </div>
            </div>

            <div class="space-y-3">
                <a href="create_interface" class="action-tile block p-4">
                    <div class="flex items-center">
                        <div class="w-11 h-11 rounded-2xl bg-green-500 bg-opacity-10 flex items-center justify-center mr-3">
                            <i class="fas fa-plus text-green-300"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-white">Create Interface</h3>
                            <p class="text-sm text-gray-400">Add a new VPN tunnel</p>
                        </div>
                    </div>
                </a>

                <a href="wg_peers" class="action-tile block p-4">
                    <div class="flex items-center">
                        <div class="w-11 h-11 rounded-2xl bg-blue-500 bg-opacity-10 flex items-center justify-center mr-3">
                            <i class="fas fa-user-friends text-blue-300"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-white">Manage Peers</h3>
                            <p class="text-sm text-gray-400">Add clients and devices</p>
                        </div>
                    </div>
                </a>

                <a href="manage_port_forwarding" class="action-tile block p-4">
                    <div class="flex items-center">
                        <div class="w-11 h-11 rounded-2xl bg-yellow-500 bg-opacity-10 flex items-center justify-center mr-3">
                            <i class="fas fa-route text-yellow-300"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-white">Port Forwarding</h3>
                            <p class="text-sm text-gray-400">Manage exposed services</p>
                        </div>
                    </div>
                </a>

                <button onclick="refresh()" class="action-tile block w-full p-4 text-left">
                    <div class="flex items-center">
                        <div class="w-11 h-11 rounded-2xl bg-purple-500 bg-opacity-10 flex items-center justify-center mr-3">
                            <i class="fas fa-sync text-purple-300"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-white">Refresh Dashboard</h3>
                            <p class="text-sm text-gray-400">Reload live server data</p>
                        </div>
                    </div>
                </button>
            </div>
        </aside>
    </section>
</div>

<script>
    function switchInterface(interfaceName) {
        if (!interfaceName) {
            return;
        }
        window.location.href = `dashboard?interface=${encodeURIComponent(interfaceName)}`;
    }

    function refresh() {
        location.reload();
    }

    function controlInterface(action) {
        if (action === 'stop' && !confirm('Are you sure you want to stop the WireGuard interface?')) {
            return;
        }

        const selector = document.getElementById('interfaceSelector');
        const currentInterface = selector ? selector.value : '';
        if (!currentInterface) {
            alert('Create or select a WireGuard interface first.');
            return;
        }

        const buttons = document.querySelectorAll('button[onclick^="controlInterface"]');
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.6';
        });

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `wg_status?interface=${encodeURIComponent(currentInterface)}`;

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'interface_action';
        actionInput.value = action;

        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }

    function refreshStats() {
        location.reload();
    }

    function updateLastUpdated() {
        const now = new Date();
        const lastUpdated = document.getElementById('lastUpdated');
        if (lastUpdated) {
            lastUpdated.textContent = now.toLocaleTimeString();
        }
    }

    setInterval(updateLastUpdated, 1000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
