<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../autoloader.php';
// Ensure $port_rules is always an array before any usage
if (!isset($port_rules) || !is_array($port_rules)) {
    $port_rules = [];
}
try {
    $db = new \WireGuardAdmin\Database();
    $auth = new \WireGuardAdmin\Auth($db, SESSION_TIMEOUT);
    $wg = new \WireGuardAdmin\WireGuard($db, WG_IFACE);

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #000000 0%, #1a365d 100%);
            --card-bg: rgba(255, 255, 255, 0.07);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: #a0aec0;
            --accent-color: #10b981;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary-gradient);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .nav-link {
            transition: all 0.3s ease;
            border-radius: 12px;
        }

        .nav-link:hover {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
        }

        .nav-link.active {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            color: var(--accent-color);
            border-left: 3px solid var(--accent-color);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .progress-bar {
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.1);
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease-in-out;
        }

        .floating-btn {
            background: linear-gradient(135deg, var(--accent-color) 0%, #059669 100%);
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
        }

        .floating-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .table-row {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s ease;
        }

        .table-row:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .stat-number {
            font-size: 1.875rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(15px);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .top-bar {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .loading-overlay {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Navigation Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 sidebar z-30" id="sidebar">
        <div class="flex flex-col h-full">
            <!-- Logo -->
            <div class="flex items-center justify-center p-6 border-b border-gray-800">
                <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-green-700 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-shield-alt text-white"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white"><?= APP_NAME ?></h1>
                    <p class="text-xs text-gray-400">v<?= APP_VERSION ?></p>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 space-y-1">
                <a href="dashboard.php" class="nav-link active flex items-center p-3">
                    <i class="fas fa-tachometer-alt mr-3 text-green-500"></i>
                    Dashboard
                </a>
                <a href="wg-peers.php" class="nav-link flex items-center p-3">
                    <i class="fas fa-users mr-3 text-blue-400"></i>
                    VPN Peers
                </a>
                <a href="port-forwarding.php" class="nav-link flex items-center p-3">
                    <i class="fas fa-network-wired mr-3 text-purple-400"></i>
                    Port Forwarding
                </a>
                <a href="settings.php" class="nav-link flex items-center p-3">
                    <i class="fas fa-cog mr-3 text-yellow-400"></i>
                    Settings
                </a>
                <a href="logs.php" class="nav-link flex items-center p-3">
                    <i class="fas fa-file-alt mr-3 text-red-400"></i>
                    Audit Logs
                </a>
            </nav>

            <!-- User Menu -->
            <div class="p-4 border-t border-gray-800">
                <div class="flex items-center mb-3">
                    <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-white"><?= htmlspecialchars($currentUser['username']) ?></p>
                        <p class="text-xs text-gray-400"><?= htmlspecialchars($currentUser['role']) ?></p>
                    </div>
                </div>
                <a href="logout.php" class="nav-link flex items-center p-2 text-red-400">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>