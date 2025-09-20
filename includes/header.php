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

        /* Mobile menu button */
        .mobile-menu-button {
            display: none;
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                z-index: 40;
                width: 70%;
                max-width: 300px;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .mobile-menu-button {
                display: block;
                z-index: 50;
                color: red;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 30;
            }

            .overlay.active {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .stat-number {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }

            .peer-grid {
                grid-template-columns: 1fr !important;
            }

            .table-responsive {
                overflow-x: auto;
            }

            table {
                min-width: 600px;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr !important;
            }

            .top-bar-content {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .status-indicator {
                margin-top: 0.5rem;
            }
        }

        #menu-toggle {
            color: var(--accent-color);
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Mobile menu button -->
    <div class="mobile-menu-button fixed top-4 left-4 z-50 lg:hidden">
        <button id="menu-toggle" class="p-2 rounded-lg bg-black bg-opacity-30 text-white">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Overlay for mobile menu -->
    <div id="overlay" class="overlay" onclick="closeMenu()"></div>

    <!-- Navigation Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 sidebar z-40 lg:z-30 transform lg:transform-none" id="sidebar">
        <div class="flex flex-col h-full">
            <!-- Logo and close button for mobile -->
            <div class="flex items-center justify-between p-6 border-b border-gray-800">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-green-700 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-shield-alt text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white"><?= APP_NAME ?></h1>
                        <p class="text-xs text-gray-400">v<?= APP_VERSION ?></p>
                    </div>
                </div>
                <button class="lg:hidden text-gray-400 hover:text-white" onclick="closeMenu()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 space-y-1">
                <a href="dashboard" class="nav-link active flex items-center p-3" onclick="closeMenuOnMobile()">
                    <i class="fas fa-tachometer-alt mr-3 text-green-500"></i>
                    Dashboard
                </a>
                   <a href="create_interface" class="nav-link flex items-center p-3" onclick="closeMenuOnMobile()">
                    <i class="fas fa-plus-circle mr-3 text-green-400"></i>
                    Create WG Interface
                </a>
                <a href="peers-and-forwarding" class="nav-link flex items-center p-3" onclick="closeMenuOnMobile()">
                    <i class="fas fa-user-friends mr-3 text-green-500"></i>
                    Peers & Forwarding
                </a>
             
                <a href="wg-status" class="nav-link flex items-center p-3" onclick="closeMenuOnMobile()">
                    <i class="fas fa-server mr-3 text-blue-400"></i>
                    WG Status
                </a>
                <a href="logs" class="nav-link flex items-center p-3" onclick="closeMenuOnMobile()">
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
                <a href="logout" class="nav-link flex items-center p-2 text-red-400" onclick="closeMenuOnMobile()">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>

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