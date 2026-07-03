<?php
$db = new \WireGuardAdmin\Database();
$auth = new \WireGuardAdmin\Auth($db, SESSION_TIMEOUT);

if ($auth->isAuthenticated()) {
    header('Location: dashboard?success=Already logged in');
    exit;
}

$username = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="img/logo.png" />
    <style>
        :root {
            --wg-green: #11c98b;
            --wg-green-dark: #047857;
            --wg-blue: #38bdf8;
            --bg-1: #020617;
            --bg-2: #08111f;
            --card: rgba(15, 23, 42, 0.78);
            --line: rgba(148, 163, 184, 0.18);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: #f8fafc;
            background:
                linear-gradient(rgba(16, 185, 129, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.045) 1px, transparent 1px),
                radial-gradient(circle at 18% 20%, rgba(17, 201, 139, 0.28), transparent 26%),
                radial-gradient(circle at 82% 12%, rgba(56, 189, 248, 0.2), transparent 28%),
                radial-gradient(circle at 70% 88%, rgba(20, 184, 166, 0.14), transparent 28%),
                linear-gradient(135deg, var(--bg-1) 0%, var(--bg-2) 46%, #0f172a 100%);
            background-size: 46px 46px, 46px 46px, auto, auto, auto, auto;
        }

        .login-shell {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            overflow: hidden;
        }

        .login-shell::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(2, 6, 23, 0.88), rgba(2, 6, 23, 0.25), rgba(2, 6, 23, 0.88));
            pointer-events: none;
        }

        .orb {
            position: absolute;
            width: 22rem;
            height: 22rem;
            border-radius: 999px;
            filter: blur(18px);
            opacity: 0.2;
            pointer-events: none;
        }

        .orb-one {
            left: -7rem;
            top: 8%;
            background: var(--wg-green);
        }

        .orb-two {
            right: -8rem;
            bottom: 4%;
            background: var(--wg-blue);
        }

        .panel {
            position: relative;
            z-index: 1;
            width: min(1120px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1.12fr) minmax(360px, 0.88fr);
            border: 1px solid var(--line);
            border-radius: 30px;
            overflow: hidden;
            background: rgba(2, 6, 23, 0.64);
            box-shadow: 0 34px 120px rgba(0, 0, 0, 0.5);
        }

        .ops-panel {
            min-height: 660px;
            padding: 2.6rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background:
                linear-gradient(135deg, rgba(15, 23, 42, 0.6), rgba(4, 120, 87, 0.28)),
                radial-gradient(circle at 28% 16%, rgba(17, 201, 139, 0.24), transparent 28%);
        }

        .login-panel {
            padding: 2.6rem;
            background: rgba(15, 23, 42, 0.84);
            backdrop-filter: blur(22px);
            border-left: 1px solid var(--line);
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 0.9rem;
            border-radius: 999px;
            background: rgba(17, 201, 139, 0.1);
            border: 1px solid rgba(17, 201, 139, 0.28);
            color: #bbf7d0;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .hero-title {
            margin-top: 2rem;
            max-width: 620px;
            font-size: clamp(2.5rem, 5vw, 4.7rem);
            line-height: 0.95;
            font-weight: 900;
            letter-spacing: -0.06em;
        }

        .hero-title span {
            color: var(--wg-green);
        }

        .hero-copy {
            max-width: 540px;
            margin-top: 1.4rem;
            color: #cbd5e1;
            font-size: 1.05rem;
            line-height: 1.7;
        }

        .network-card {
            border: 1px solid var(--line);
            background: rgba(15, 23, 42, 0.72);
            border-radius: 24px;
            padding: 1.2rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .node-line {
            position: relative;
            height: 110px;
            margin: 1.2rem 0;
        }

        .node-line::before,
        .node-line::after {
            content: "";
            position: absolute;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(17, 201, 139, 0.9), transparent);
            top: 50%;
            left: 10%;
            right: 10%;
        }

        .node-line::after {
            transform: rotate(-18deg);
            opacity: 0.5;
        }

        .node {
            position: absolute;
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            border: 1px solid rgba(17, 201, 139, 0.4);
            background: rgba(2, 6, 23, 0.88);
            color: var(--wg-green);
            box-shadow: 0 0 26px rgba(17, 201, 139, 0.18);
            z-index: 2;
        }

        .node.server {
            left: 4%;
            top: 28px;
        }

        .node.shield {
            left: 50%;
            top: 0;
            transform: translateX(-50%);
            width: 70px;
            height: 70px;
            border-radius: 24px;
            color: #fff;
            background: linear-gradient(135deg, var(--wg-green), var(--wg-green-dark));
        }

        .node.peer {
            right: 4%;
            top: 28px;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .stat-box {
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 18px;
            padding: 1rem;
            background: rgba(2, 6, 23, 0.48);
        }

        .stat-box strong {
            display: block;
            font-size: 1.15rem;
            color: #fff;
        }

        .stat-box span {
            display: block;
            margin-top: 0.2rem;
            color: #94a3b8;
            font-size: 0.78rem;
        }

        .console {
            margin-top: 1rem;
            border-radius: 18px;
            border: 1px solid rgba(17, 201, 139, 0.18);
            background: rgba(2, 6, 23, 0.68);
            padding: 1rem;
            font-family: Consolas, Monaco, monospace;
            color: #86efac;
            font-size: 0.8rem;
        }

        .console p {
            margin: 0.35rem 0;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            color: #64748b;
        }

        .auth-input {
            width: 100%;
            padding: 1rem 1rem 1rem 2.85rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 16px;
            outline: none;
            color: #fff;
            background: rgba(2, 6, 23, 0.58);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .auth-input:focus {
            border-color: rgba(17, 201, 139, 0.8);
            box-shadow: 0 0 0 4px rgba(17, 201, 139, 0.12);
            background: rgba(2, 6, 23, 0.82);
        }

        .auth-button {
            background: linear-gradient(135deg, var(--wg-green) 0%, var(--wg-green-dark) 100%);
            box-shadow: 0 18px 42px rgba(17, 201, 139, 0.24);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .auth-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 22px 54px rgba(17, 201, 139, 0.32);
        }

        .alert {
            border-radius: 18px;
            padding: 1rem;
            margin-bottom: 1.2rem;
        }

        @media (max-width: 900px) {
            .panel {
                grid-template-columns: 1fr;
            }

            .ops-panel {
                min-height: auto;
                padding: 2rem;
            }

            .login-panel {
                border-left: 0;
                border-top: 1px solid var(--line);
                padding: 2rem;
            }
        }

        @media (max-width: 560px) {
            .login-shell {
                padding: 0.8rem;
            }

            .ops-panel,
            .login-panel {
                padding: 1.35rem;
            }

            .stat-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <main class="login-shell">
        <div class="orb orb-one"></div>
        <div class="orb orb-two"></div>

        <section class="panel">
            <aside class="ops-panel">
                <div>
                    <div class="brand-badge">
                        <i class="fas fa-shield-alt"></i>
                        WIREGUARD VPN ADMIN
                    </div>

                    <h1 class="hero-title">
                        Control your <span>secure tunnel</span> network.
                    </h1>
                    <p class="hero-copy">
                        A modern control panel for WireGuard interfaces, peers, firewall access, and VPN operations.
                    </p>
                </div>

                <div class="network-card">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <p class="text-sm uppercase tracking-widest text-green-300 font-bold">Live Gateway</p>
                            <h2 class="text-xl font-bold text-white"><?= APP_NAME ?></h2>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-500 bg-opacity-10 text-green-300 text-xs font-bold">
                            <span class="w-2 h-2 rounded-full bg-green-400 mr-2"></span>
                            Protected
                        </span>
                    </div>

                    <div class="node-line">
                        <div class="node server"><i class="fas fa-server"></i></div>
                        <div class="node shield"><i class="fas fa-lock"></i></div>
                        <div class="node peer"><i class="fas fa-laptop"></i></div>
                    </div>

                    <div class="stat-grid">
                        <div class="stat-box">
                            <strong>WG</strong>
                            <span>Protocol</span>
                        </div>
                        <div class="stat-box">
                            <strong>256-bit</strong>
                            <span>Encryption</span>
                        </div>
                        <div class="stat-box">
                            <strong>Admin</strong>
                            <span>Access</span>
                        </div>
                    </div>

                    <div class="console">
                        <p>$ wg show interfaces</p>
                        <p>&gt; secure panel ready</p>
                        <p>&gt; authenticate admin session</p>
                    </div>
                </div>
            </aside>

            <section class="login-panel">
                <div class="mb-8">
                    <p class="text-sm font-bold text-green-400 mb-2">Panel v<?= APP_VERSION ?></p>
                    <h2 class="text-3xl font-extrabold text-white mb-3">Admin Sign In</h2>
                    <p class="text-gray-400">Enter your VPN administrator credentials to unlock the dashboard.</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert bg-green-500 bg-opacity-10 border border-green-400 border-opacity-30 text-green-100" role="alert">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert bg-red-500 bg-opacity-10 border border-red-400 border-opacity-30 text-red-100" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5" autocomplete="on" action="app/backend/auth_backend.php">
                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-200 mb-2">Admin Username</label>
                        <div class="input-wrap">
                            <i class="fas fa-user-shield"></i>
                            <input type="text" id="username" name="username" required autocomplete="username"
                                class="auth-input"
                                placeholder="admin"
                                value="<?= $username ?>">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-200 mb-2">Admin Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-key"></i>
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                class="auth-input"
                                placeholder="Enter password">
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="inline-flex items-center text-gray-300">
                            <input type="checkbox" name="remember_me" class="mr-2 rounded border-gray-600 bg-gray-800 text-green-500">
                            Trust this device
                        </label>
                        <span class="text-gray-500">Secure console</span>
                    </div>

                    <button type="submit" class="auth-button w-full text-white py-4 rounded-2xl font-bold">
                        <i class="fas fa-sign-in-alt mr-2"></i>Open VPN Panel
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t border-gray-700 border-opacity-60">
                    <div class="flex items-center text-sm text-gray-400">
                        <i class="fas fa-info-circle text-green-400 mr-3"></i>
                        Access is logged for VPN security auditing.
                    </div>
                </div>
            </section>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const form = document.querySelector('form');

            if (usernameInput && !usernameInput.value) {
                usernameInput.focus();
            } else if (passwordInput) {
                passwordInput.focus();
            }

            form.addEventListener('submit', function() {
                const button = form.querySelector('button[type="submit"]');
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Opening Panel...';
                button.disabled = true;
            });
        });
    </script>
</body>

</html>
