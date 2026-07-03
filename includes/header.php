<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

if (!is_authenticated()) {
    header('Location: login?error=Please+login to access+the dashboard');
    exit;
}

if (!isset($port_rules) || !is_array($port_rules)) {
    $port_rules = [];
}

try {
    $db = new \WireGuardAdmin\Database();
    $auth = new \WireGuardAdmin\Auth($db, SESSION_TIMEOUT);
    $wg = new \WireGuardAdmin\WireGuard($db);

    $auth->requireAuth('/login.php');

    $currentUser = $auth->getCurrentUser();
    $wg->updatePeerStats();
    $wgStatus = $wg->getStatus();
    $isRunning = $wg->isRunning();
    $peers = $wg->getPeers();
    $systemStats = $wg->getSystemStats();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$currentRoute = trim(basename($requestPath), '/');
if ($currentRoute === '' || $currentRoute === 'wireguard' || $currentRoute === 'index.php') {
    $currentRoute = 'dashboard';
}

$pageTitles = [
    'dashboard' => 'Dashboard',
    'create_interface' => 'WG Interface',
    'wg_peers' => 'WG Peers',
    'wg_status' => 'WG Status',
    'logs' => 'Audit Logs',
    'manage_port_forwarding' => 'Port Forwarding',
    'change_password' => 'Change Password'
];
$pageTitle = $pageTitles[$currentRoute] ?? 'VPN Panel';
$currentUsername = htmlspecialchars($currentUser['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$currentRole = htmlspecialchars($currentUser['role'] ?? 'admin', ENT_QUOTES, 'UTF-8');

$navItems = [
    ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'color' => 'text-green-400'],
    ['route' => 'create_interface', 'label' => 'Interfaces', 'icon' => 'fa-network-wired', 'color' => 'text-cyan-400'],
    ['route' => 'wg_peers', 'label' => 'Peers', 'icon' => 'fa-user-friends', 'color' => 'text-blue-400'],
    ['route' => 'wg_status', 'label' => 'WG Status', 'icon' => 'fa-server', 'color' => 'text-purple-400'],
    ['route' => 'manage_port_forwarding', 'label' => 'Port Forwarding', 'icon' => 'fa-route', 'color' => 'text-yellow-400'],
    ['route' => 'logs', 'label' => 'Audit Logs', 'icon' => 'fa-file-alt', 'color' => 'text-red-400']
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="img/logo.png" />

    <style>
        :root {
            --bg-main: #020617;
            --bg-panel: rgba(15, 23, 42, 0.82);
            --bg-card: rgba(15, 23, 42, 0.7);
            --border-soft: rgba(148, 163, 184, 0.16);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-color: #10b981;
            --accent-strong: #14f1a4;
            --accent-blue: #38bdf8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            background:
                linear-gradient(rgba(16, 185, 129, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.035) 1px, transparent 1px),
                radial-gradient(circle at 12% 12%, rgba(16, 185, 129, 0.2), transparent 30%),
                radial-gradient(circle at 84% 10%, rgba(56, 189, 248, 0.14), transparent 28%),
                radial-gradient(circle at 70% 86%, rgba(20, 184, 166, 0.12), transparent 30%),
                linear-gradient(135deg, #020617 0%, #07111f 48%, #0f172a 100%);
            background-size: 44px 44px, 44px 44px, auto, auto, auto, auto;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(90deg, rgba(2, 6, 23, 0.82), rgba(2, 6, 23, 0.2), rgba(2, 6, 23, 0.75));
            z-index: -1;
        }

        a,
        button,
        input,
        select,
        textarea {
            transition: border-color 0.2s ease, background 0.2s ease, color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        input,
        select,
        textarea,
        .form-input {
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(2, 6, 23, 0.52);
            color: #fff;
        }

        input:focus,
        select:focus,
        textarea:focus,
        .form-input:focus {
            outline: none;
            border-color: rgba(16, 185, 129, 0.85);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
        }

        select option {
            background: #0f172a;
            color: #fff;
        }

        .app-sidebar {
            background: rgba(2, 6, 23, 0.86);
            border-right: 1px solid var(--border-soft);
            backdrop-filter: blur(24px);
            box-shadow: 16px 0 60px rgba(0, 0, 0, 0.28);
        }

        .brand-mark {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--accent-color), #047857);
        }

        .brand-mark::after {
            content: "";
            position: absolute;
            inset: -40%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.35), transparent);
            transform: rotate(25deg) translateX(-120%);
            animation: shine 4s ease-in-out infinite;
        }

        @keyframes shine {
            0%, 45% {
                transform: rotate(25deg) translateX(-120%);
            }
            70%, 100% {
                transform: rotate(25deg) translateX(120%);
            }
        }

        .nav-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.78rem 0.9rem;
            border-radius: 16px;
            color: #cbd5e1;
            font-weight: 650;
            border: 1px solid transparent;
        }

        .nav-link:hover {
            color: #fff;
            background: rgba(148, 163, 184, 0.1);
            border-color: rgba(148, 163, 184, 0.13);
        }

        .nav-link.active {
            color: #fff;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(56, 189, 248, 0.1));
            border-color: rgba(16, 185, 129, 0.32);
            box-shadow: 0 16px 35px rgba(16, 185, 129, 0.08);
        }

        .nav-link.active::before {
            content: "";
            width: 4px;
            height: 28px;
            border-radius: 999px;
            background: var(--accent-strong);
            position: absolute;
            left: -0.1rem;
        }

        .vpn-animation-card {
            border: 1px solid rgba(16, 185, 129, 0.18);
            border-radius: 22px;
            background: linear-gradient(145deg, rgba(16, 185, 129, 0.13), rgba(2, 6, 23, 0.72));
            overflow: hidden;
            position: relative;
        }

        .vpn-animation-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 30%, rgba(20, 241, 164, 0.18), transparent 35%);
            pointer-events: none;
        }

        .tunnel-map {
            position: relative;
            height: 86px;
        }

        .tunnel-map::before,
        .tunnel-map::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 16%;
            right: 16%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent-strong), transparent);
            animation: tunnelPulse 2.8s ease-in-out infinite;
        }

        .tunnel-map::after {
            transform: rotate(-18deg);
            opacity: 0.45;
            animation-delay: 0.5s;
        }

        @keyframes tunnelPulse {
            0%, 100% {
                opacity: 0.25;
                filter: drop-shadow(0 0 0 rgba(20, 241, 164, 0));
            }
            50% {
                opacity: 1;
                filter: drop-shadow(0 0 12px rgba(20, 241, 164, 0.7));
            }
        }

        .vpn-node {
            position: absolute;
            top: 50%;
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            border: 1px solid rgba(16, 185, 129, 0.38);
            background: rgba(2, 6, 23, 0.84);
            color: var(--accent-strong);
            transform: translateY(-50%);
            z-index: 1;
        }

        .vpn-node.left {
            left: 6%;
        }

        .vpn-node.center {
            left: 50%;
            width: 48px;
            height: 48px;
            color: #fff;
            background: linear-gradient(135deg, var(--accent-color), #047857);
            transform: translate(-50%, -50%);
            box-shadow: 0 0 32px rgba(16, 185, 129, 0.32);
            animation: nodeFloat 3s ease-in-out infinite;
        }

        .vpn-node.right {
            right: 6%;
        }

        @keyframes nodeFloat {
            0%, 100% {
                transform: translate(-50%, -50%);
            }
            50% {
                transform: translate(-50%, -62%);
            }
        }

        .top-bar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(2, 6, 23, 0.62);
            border-bottom: 1px solid var(--border-soft);
            backdrop-filter: blur(20px);
        }

        .glass-card,
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 22px;
            backdrop-filter: blur(18px);
            box-shadow: 0 18px 70px rgba(0, 0, 0, 0.24);
        }

        .glass-card:hover {
            transform: translateY(-2px);
            border-color: rgba(16, 185, 129, 0.28);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.32);
        }

        .stat-number {
            font-size: 1.875rem;
            font-weight: 850;
            background: linear-gradient(135deg, #fff 0%, #bbf7d0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 750;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(15, 23, 42, 0.58);
        }

        .progress-bar {
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(148, 163, 184, 0.14);
        }

        .progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.5s ease-in-out;
        }

        .floating-btn,
        .btn-submit,
        button[type="submit"] {
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent-color), #047857);
            box-shadow: 0 14px 36px rgba(16, 185, 129, 0.22);
        }

        .floating-btn:hover,
        .btn-submit:hover,
        button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 46px rgba(16, 185, 129, 0.32);
        }

        .table-row {
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            transition: background 0.2s ease;
        }

        .table-row:hover,
        tbody tr:hover {
            background: rgba(16, 185, 129, 0.055);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            inset: 0;
            background: rgba(2, 6, 23, 0.74);
            backdrop-filter: blur(10px);
            animation: fadeIn 0.25s ease;
        }

        .modal-title {
            color: var(--accent-strong);
            font-weight: 800;
        }

        .close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: #cbd5e1;
            font-size: 1.6rem;
        }

        .close-btn:hover {
            color: #f87171;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .mobile-menu-button {
            display: none;
        }

        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.72);
            backdrop-filter: blur(4px);
            z-index: 30;
        }

        .overlay.active {
            display: block;
        }

        @media (max-width: 1024px) {
            .app-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                z-index: 40;
                width: 82%;
                max-width: 330px;
            }

            .app-sidebar.open {
                transform: translateX(0);
            }

            .mobile-menu-button {
                display: block;
            }

            .main-content {
                margin-left: 0 !important;
            }
        }

        @media (max-width: 768px) {
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
                align-items: flex-start !important;
                flex-direction: column;
            }

            .status-indicator {
                margin-top: 0.75rem;
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>

<body class="min-h-screen">
    <div class="mobile-menu-button fixed top-4 left-4 z-50 lg:hidden">
        <button id="menu-toggle" class="w-11 h-11 rounded-2xl bg-gray-900 bg-opacity-80 border border-gray-700 text-green-300 shadow-lg">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div id="overlay" class="overlay" onclick="closeMenu()"></div>

    <aside class="fixed inset-y-0 left-0 w-72 app-sidebar z-40 lg:z-30 transform lg:transform-none" id="sidebar">
        <div class="flex flex-col h-full">
            <div class="p-5 border-b border-gray-800 border-opacity-80">
                <div class="flex items-center justify-between">
                    <div class="flex items-center min-w-0">
                        <div class="brand-mark w-12 h-12 rounded-2xl flex items-center justify-center mr-3 flex-shrink-0">
                            <i class="fas fa-shield-alt text-white"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-lg font-black text-white truncate"><?= APP_NAME ?></h1>
                            <p class="text-xs text-gray-400">WireGuard VPN Panel v<?= APP_VERSION ?></p>
                        </div>
                    </div>
                    <button class="lg:hidden text-gray-400 hover:text-white" onclick="closeMenu()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="vpn-animation-card mt-5 p-4">
                    <div class="flex items-center justify-between relative z-10">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-green-300 font-bold">VPN Tunnel</p>
                            <p class="text-sm text-gray-300 mt-1"><?= $isRunning ? 'Encrypted traffic active' : 'Interface is stopped' ?></p>
                        </div>
                        <span class="status-badge <?= $isRunning ? 'text-green-300' : 'text-red-300' ?>">
                            <span class="w-2 h-2 rounded-full <?= $isRunning ? 'bg-green-400 animate-pulse' : 'bg-red-400' ?>"></span>
                            <?= $isRunning ? 'Live' : 'Offline' ?>
                        </span>
                    </div>
                    <div class="tunnel-map">
                        <div class="vpn-node left"><i class="fas fa-server"></i></div>
                        <div class="vpn-node center"><i class="fas fa-lock"></i></div>
                        <div class="vpn-node right"><i class="fas fa-laptop"></i></div>
                    </div>
                </div>
            </div>

            <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
                <p class="px-3 pb-1 text-xs uppercase tracking-widest text-gray-500 font-bold">Navigation</p>
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = $currentRoute === $item['route']; ?>
                    <a href="<?= htmlspecialchars($item['route'], ENT_QUOTES, 'UTF-8') ?>"
                        class="nav-link <?= $isActive ? 'active' : '' ?>"
                        onclick="closeMenuOnMobile()">
                        <i class="fas <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?> w-5 text-center"></i>
                        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="p-4 border-t border-gray-800 border-opacity-80">
                <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-3 mb-3">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-2xl bg-green-500 bg-opacity-15 flex items-center justify-center mr-3">
                            <i class="fas fa-user-shield text-green-300"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-white truncate"><?= $currentUsername ?></p>
                            <p class="text-xs text-gray-400 capitalize"><?= $currentRole ?></p>
                        </div>
                    </div>
                </div>
                <a href="change_password" class="nav-link text-green-300" onclick="closeMenuOnMobile()">
                    <i class="fas fa-key w-5 text-center"></i>
                    <span>Change Password</span>
                </a>
                <a href="logout" class="nav-link text-red-300 mt-2" onclick="closeMenuOnMobile()">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <div class="main-content ml-0 lg:ml-72 min-h-screen">
        <header class="top-bar p-4 lg:p-5">
            <div class="flex items-center justify-between top-bar-content">
                <div class="pl-12 lg:pl-0">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="hidden sm:inline-flex w-2.5 h-2.5 rounded-full <?= $isRunning ? 'bg-green-400 animate-pulse' : 'bg-red-400' ?>"></span>
                        <p class="text-xs uppercase tracking-widest text-gray-500 font-bold">Control Center</p>
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-black text-white"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-gray-400 mt-1">Welcome back, <?= $currentUsername ?>. Manage your VPN network from here.</p>
                </div>
                <div class="flex items-center gap-3 status-indicator">
                    <div class="hidden md:flex items-center rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 px-4 py-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center mr-3 <?= $isRunning ? 'bg-green-500 bg-opacity-15 text-green-300' : 'bg-red-500 bg-opacity-15 text-red-300' ?>">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">WireGuard</p>
                            <p class="text-sm font-bold <?= $isRunning ? 'text-green-300' : 'text-red-300' ?>">
                                <?= $isRunning ? 'Running' : 'Stopped' ?>
                            </p>
                        </div>
                    </div>
                    <a href="wg_status" class="w-11 h-11 rounded-2xl flex items-center justify-center bg-white bg-opacity-5 border border-white border-opacity-10 text-gray-300 hover:text-green-300 hover:bg-opacity-10">
                        <i class="fas fa-chart-line"></i>
                    </a>
                </div>
            </div>
        </header>

        <?php if (isset($_GET['success'])): ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: "<?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?>",
                    confirmButtonColor: '#10b981',
                    background: '#0f172a',
                    color: '#f8fafc'
                });
            </script>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: "<?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>",
                    confirmButtonColor: '#ef4444',
                    background: '#0f172a',
                    color: '#f8fafc'
                });
            </script>
        <?php endif; ?>
