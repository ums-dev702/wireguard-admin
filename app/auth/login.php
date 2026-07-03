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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="img/logo.png" />
    <style>
        :root {
            --accent: #10b981;
            --accent-dark: #047857;
            --panel: rgba(15, 23, 42, 0.82);
            --panel-border: rgba(148, 163, 184, 0.18);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at 15% 20%, rgba(16, 185, 129, 0.24), transparent 28%),
                radial-gradient(circle at 85% 10%, rgba(59, 130, 246, 0.22), transparent 30%),
                linear-gradient(135deg, #020617 0%, #0f172a 48%, #111827 100%);
            color: #fff;
            min-height: 100vh;
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .auth-shell::before,
        .auth-shell::after {
            content: "";
            position: absolute;
            border-radius: 9999px;
            filter: blur(8px);
            opacity: 0.4;
            pointer-events: none;
        }

        .auth-shell::before {
            width: 18rem;
            height: 18rem;
            left: -6rem;
            bottom: 8%;
            background: rgba(16, 185, 129, 0.22);
        }

        .auth-shell::after {
            width: 16rem;
            height: 16rem;
            right: -5rem;
            top: 8%;
            background: rgba(37, 99, 235, 0.22);
        }

        .auth-card {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(340px, 0.92fr);
            overflow: hidden;
            border: 1px solid var(--panel-border);
            border-radius: 28px;
            background: rgba(2, 6, 23, 0.62);
            box-shadow: 0 30px 100px rgba(0, 0, 0, 0.42);
            position: relative;
            z-index: 1;
        }

        .brand-panel {
            min-height: 620px;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background:
                linear-gradient(145deg, rgba(16, 185, 129, 0.92), rgba(4, 120, 87, 0.75)),
                url("data:image/svg+xml,%3Csvg width='120' height='120' viewBox='0 0 120 120' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='rgba(255,255,255,0.18)' stroke-width='1'%3E%3Cpath d='M0 60h120M60 0v120'/%3E%3Ccircle cx='60' cy='60' r='34'/%3E%3C/g%3E%3C/svg%3E");
        }

        .login-panel {
            padding: 3rem;
            background: var(--panel);
            backdrop-filter: blur(22px);
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .auth-input {
            width: 100%;
            padding: 0.95rem 1rem 0.95rem 2.8rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.74);
            color: #fff;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .auth-input:focus {
            border-color: rgba(16, 185, 129, 0.75);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
            background: rgba(15, 23, 42, 0.95);
        }

        .auth-button {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            box-shadow: 0 16px 35px rgba(16, 185, 129, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .auth-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 45px rgba(16, 185, 129, 0.34);
        }

        .status-pill {
            border: 1px solid rgba(255, 255, 255, 0.22);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        @media (max-width: 860px) {
            .auth-card {
                grid-template-columns: 1fr;
            }

            .brand-panel {
                min-height: auto;
                padding: 2rem;
                gap: 3rem;
            }

            .login-panel {
                padding: 2rem;
            }
        }

        @media (max-width: 480px) {
            .auth-shell {
                padding: 0.75rem;
            }

            .brand-panel,
            .login-panel {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <main class="auth-shell">
        <section class="auth-card">
            <aside class="brand-panel">
                <div>
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white bg-opacity-20 mb-6">
                        <i class="fas fa-shield-alt text-2xl"></i>
                    </div>
                    <p class="uppercase tracking-widest text-sm font-semibold text-green-50 text-opacity-80 mb-3">Secure VPN control</p>
                    <h1 class="text-4xl lg:text-5xl font-extrabold leading-tight mb-5"><?= APP_NAME ?></h1>
                    <p class="text-green-50 text-opacity-90 text-lg max-w-md">
                        Manage WireGuard interfaces, peers, and access from one protected admin dashboard.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="status-pill rounded-2xl p-4">
                        <i class="fas fa-lock mb-3"></i>
                        <p class="text-sm font-semibold">Encrypted</p>
                    </div>
                    <div class="status-pill rounded-2xl p-4">
                        <i class="fas fa-server mb-3"></i>
                        <p class="text-sm font-semibold">Server Ready</p>
                    </div>
                    <div class="status-pill rounded-2xl p-4">
                        <i class="fas fa-tachometer-alt mb-3"></i>
                        <p class="text-sm font-semibold">Fast Access</p>
                    </div>
                </div>
            </aside>

            <section class="login-panel">
                <div class="mb-8">
                    <p class="text-sm font-semibold text-green-400 mb-2">Version <?= APP_VERSION ?></p>
                    <h2 class="text-3xl font-bold text-white mb-3">Welcome back</h2>
                    <p class="text-gray-400">Sign in with your administrator credentials to continue.</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-500 bg-opacity-10 border border-green-400 border-opacity-30 text-green-100 p-4 mb-5 rounded-2xl" role="alert">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="bg-red-500 bg-opacity-10 border border-red-400 border-opacity-30 text-red-100 p-4 mb-5 rounded-2xl" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5" autocomplete="on" action="app/backend/auth_backend.php">
                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-200 mb-2">Username</label>
                        <div class="input-wrap">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username" required autocomplete="username"
                                class="auth-input"
                                placeholder="admin"
                                value="<?= $username ?>">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-200 mb-2">Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-key"></i>
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                class="auth-input"
                                placeholder="Enter your password">
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="inline-flex items-center text-gray-300">
                            <input type="checkbox" name="remember_me" class="mr-2 rounded border-gray-600 bg-gray-800 text-green-500">
                            Remember this device
                        </label>
                        <span class="text-gray-500">Admin only</span>
                    </div>

                    <button type="submit" class="auth-button w-full text-white py-4 rounded-2xl font-bold">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                </form>
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
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
                button.disabled = true;
            });
        });
    </script>
</body>

</html>
