<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';
// Check if installation is complete
$db = new \WireGuardAdmin\Database();
$auth = new \WireGuardAdmin\Auth($db, SESSION_TIMEOUT);
$error = '';
$success = '';

// Redirect if already authenticated
if ($auth->isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);

    // Basic rate limiting (simple implementation)
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastAttempt = $_SESSION['last_attempt'] ?? 0;

    if ($attempts >= MAX_LOGIN_ATTEMPTS && (time() - $lastAttempt) < 300) {
        $error = "Too many failed attempts. Please try again in 5 minutes.";
    } else {
        if ($auth->login($username, $password, $rememberMe)) {
            // Reset attempts on successful login
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_attempt']);

            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['login_attempts'] = $attempts + 1;
            $_SESSION['last_attempt'] = time();
            $error = "Invalid credentials";
        }
    }
}

$csrfToken = $auth->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #000000 0%, #1a365d 100%);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .input-focus {
            transition: all 0.3s ease;
        }

        .input-focus:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            background: rgba(255, 255, 255, 0.15);
        }

        .error-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .vpn-icon {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .btn-login {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float-particles 20s infinite linear;
        }

        @keyframes float-particles {
            0% {
                transform: translateY(100vh) translateX(0px);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(-100px) translateX(100px);
                opacity: 0;
            }
        }
    </style>
</head>

<body class="gradient-bg min-h-screen relative overflow-y-auto">

    <!-- Animated Particles Background -->
    <div class="particles">
        <div class="particle" style="left: 10%; width: 6px; height: 6px; animation-delay: 0s;"></div>
        <div class="particle" style="left: 20%; width: 8px; height: 8px; animation-delay: 2s;"></div>
        <div class="particle" style="left: 30%; width: 4px; height: 4px; animation-delay: 4s;"></div>
        <div class="particle" style="left: 40%; width: 10px; height: 10px; animation-delay: 6s;"></div>
        <div class="particle" style="left: 50%; width: 6px; height: 6px; animation-delay: 8s;"></div>
        <div class="particle" style="left: 60%; width: 8px; height: 8px; animation-delay: 10s;"></div>
        <div class="particle" style="left: 70%; width: 4px; height: 4px; animation-delay: 12s;"></div>
        <div class="particle" style="left: 80%; width: 6px; height: 6px; animation-delay: 14s;"></div>
        <div class="particle" style="left: 90%; width: 8px; height: 8px; animation-delay: 16s;"></div>
    </div>

    <div class="relative min-h-screen flex items-center justify-center p-4 z-10">
        <div class="w-full max-w-md fade-in">
            <!-- Logo and Title -->
            <div class="text-center mb-8">
                <div class="inline-block glass-effect rounded-full p-6 mb-4">
                    <i class="fas fa-shield-alt text-4xl text-white vpn-icon"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2"><?= APP_NAME ?></h1>
                <p class="text-gray-200">Professional VPN Management</p>
                <div class="text-xs text-gray-300 mt-2">
                    <i class="fas fa-code mr-1"></i>Version <?= APP_VERSION ?>
                </div>
            </div>

            <!-- Login Form -->
            <div class="glass-effect rounded-2xl p-8 backdrop-blur-lg">
                <h2 class="text-2xl font-bold text-center text-white mb-6">Welcome Back</h2>

                <p class="text-gray-300 mb-6 text-center">Please enter your credentials to access the dashboard.</p>
                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-500 bg-opacity-20 border border-green-500 border-opacity-50 text-green-200 p-4 mb-6 rounded-lg" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?= htmlspecialchars($_GET['success']) ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="bg-red-500 bg-opacity-20 border border-red-500 border-opacity-50 text-red-200 p-4 mb-6 rounded-lg error-shake" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <?= htmlspecialchars($_GET['error']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-500 bg-opacity-20 border border-red-500 border-opacity-50 text-red-200 p-4 mb-6 rounded-lg error-shake" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-500 bg-opacity-20 border border-green-500 border-opacity-50 text-green-200 p-4 mb-6 rounded-lg" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-200 mb-2">
                            <i class="fas fa-user mr-2"></i>Username
                        </label>
                        <input type="text" id="username" name="username" required autocomplete="username"
                            class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 input-focus focus:outline-none"
                            placeholder="Enter your username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-200 mb-2">
                            <i class="fas fa-lock mr-2"></i>Password
                        </label>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 input-focus focus:outline-none"
                            placeholder="Enter your password">
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember_me" name="remember_me"
                                class="w-4 h-4 text-green-600 bg-white bg-opacity-20 border-gray-300 rounded focus:ring-green-500">
                            <label for="remember_me" class="ml-2 text-sm text-gray-200">
                                Remember me
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="w-full btn-login text-white py-3 rounded-lg font-semibold">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8">
                <p class="text-gray-300 text-sm">
                    <i class="fas fa-shield-alt mr-2"></i>
                    Secured with enterprise-grade encryption
                </p>
                <div class="flex justify-center items-center mt-4 space-x-4 text-gray-400 text-xs">
                    <span><i class="fas fa-server mr-1"></i>High Performance</span>
                    <span><i class="fas fa-lock mr-1"></i>Secure</span>
                    <span><i class="fas fa-tachometer-alt mr-1"></i>Fast</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Focus management
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            if (usernameInput && !usernameInput.value) {
                usernameInput.focus();
            } else if (passwordInput) {
                passwordInput.focus();
            }

            // Form validation feedback
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const button = form.querySelector('button[type="submit"]');
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
                button.disabled = true;
            });

            // Remove shake animation after it completes
            const errorDiv = document.querySelector('.error-shake');
            if (errorDiv) {
                setTimeout(() => {
                    errorDiv.classList.remove('error-shake');
                }, 500);
            }
        });
    </script>
</body>

</html>