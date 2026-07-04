<?php
require_once __DIR__ . '/../includes/header.php';

$csrfToken = $auth->generateCSRFToken();
$username = htmlspecialchars($currentUser['username'] ?? 'admin', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($currentUser['role'] ?? 'admin', ENT_QUOTES, 'UTF-8');
?>

<style>
    .security-hero {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        border: 1px solid rgba(16, 185, 129, 0.18);
        background:
            radial-gradient(circle at 14% 20%, rgba(20, 241, 164, 0.24), transparent 32%),
            radial-gradient(circle at 86% 18%, rgba(56, 189, 248, 0.16), transparent 30%),
            linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(2, 6, 23, 0.76));
        box-shadow: 0 26px 90px rgba(0, 0, 0, 0.3);
    }

    .security-hero::before {
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

    .security-card {
        border: 1px solid rgba(148, 163, 184, 0.16);
        border-radius: 28px;
        background: rgba(15, 23, 42, 0.74);
        backdrop-filter: blur(18px);
        box-shadow: 0 22px 80px rgba(0, 0, 0, 0.24);
    }

    .security-input {
        width: 100%;
        padding: 1rem 1rem 1rem 2.9rem;
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: rgba(2, 6, 23, 0.58);
        color: #fff;
    }

    .security-input:focus {
        outline: none;
        border-color: rgba(16, 185, 129, 0.85);
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
    }

    .input-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
    }

    .security-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.55rem;
        border-radius: 18px;
        padding: 0.95rem 1.1rem;
        font-weight: 850;
    }

    .security-button-primary {
        color: #fff;
        background: linear-gradient(135deg, #10b981, #047857);
        box-shadow: 0 16px 40px rgba(16, 185, 129, 0.24);
    }

    .security-button-secondary {
        color: #e2e8f0;
        border: 1px solid rgba(148, 163, 184, 0.18);
        background: rgba(255, 255, 255, 0.06);
    }

    .security-orb {
        width: 92px;
        height: 92px;
        display: grid;
        place-items: center;
        border-radius: 32px;
        border: 1px solid rgba(16, 185, 129, 0.28);
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.26), rgba(56, 189, 248, 0.12));
        box-shadow: 0 0 48px rgba(16, 185, 129, 0.18);
    }
</style>

<div class="p-4 lg:p-6 space-y-6">
    <section class="security-hero p-5 lg:p-7">
        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div class="flex items-start gap-5">
                <div class="security-orb hidden md:grid">
                    <i class="fas fa-user-shield text-4xl text-green-300"></i>
                </div>
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full border border-green-400 border-opacity-20 bg-green-500 bg-opacity-10 text-green-300 text-sm font-bold mb-4">
                        <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse mr-2"></span>
                        Admin Security
                    </span>
                    <h1 class="text-3xl lg:text-5xl font-black text-white leading-tight">Change Admin Password</h1>
                    <p class="text-gray-300 text-base lg:text-lg mt-4 max-w-3xl">
                        Keep your VPN control panel protected with a strong administrator password.
                    </p>
                </div>
            </div>

            <div class="rounded-3xl bg-white bg-opacity-5 border border-white border-opacity-10 p-5 min-w-full sm:min-w-72">
                <p class="text-xs uppercase tracking-widest text-gray-500 font-bold">Current Account</p>
                <p class="text-xl font-black text-white mt-2"><?= $username ?></p>
                <p class="text-sm text-green-300 capitalize mt-1"><?= $role ?></p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 security-card p-5 lg:p-7">
            <div class="mb-6">
                <p class="text-sm uppercase tracking-widest text-green-300 font-bold">Password Update</p>
                <h2 class="text-2xl font-black text-white mt-1">Secure Credentials</h2>
                <p class="text-gray-400 mt-2">Confirm your current password, then choose a new one.</p>
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

            <form method="POST" action="app/backend/change_password_backend.php" class="space-y-5" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label for="current_password" class="block text-sm font-bold text-gray-200 mb-2">Current Password</label>
                    <div class="relative">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                            class="security-input" placeholder="Enter current password">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="new_password" class="block text-sm font-bold text-gray-200 mb-2">New Password</label>
                        <div class="relative">
                            <i class="fas fa-key input-icon"></i>
                            <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8"
                                class="security-input" placeholder="Minimum 8 characters">
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-bold text-gray-200 mb-2">Confirm Password</label>
                        <div class="relative">
                            <i class="fas fa-check input-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" minlength="8"
                                class="security-input" placeholder="Repeat new password">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit" class="security-button security-button-primary flex-1">
                        <i class="fas fa-shield-alt"></i>Update Password
                    </button>
                    <a href="dashboard" class="security-button security-button-secondary flex-1">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <aside class="security-card p-5 lg:p-6">
            <p class="text-sm uppercase tracking-widest text-blue-300 font-bold">Security Tips</p>
            <h2 class="text-2xl font-black text-white mt-1 mb-5">VPN Admin Safety</h2>

            <div class="space-y-3">
                <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
                    <i class="fas fa-fingerprint text-green-300 mb-3"></i>
                    <p class="text-white font-bold">Use a unique password</p>
                    <p class="text-sm text-gray-400 mt-1">Do not reuse your server, database, or router password.</p>
                </div>
                <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
                    <i class="fas fa-clock text-blue-300 mb-3"></i>
                    <p class="text-white font-bold">Rotate regularly</p>
                    <p class="text-sm text-gray-400 mt-1">Change admin credentials after maintenance or staff changes.</p>
                </div>
                <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
                    <i class="fas fa-user-lock text-purple-300 mb-3"></i>
                    <p class="text-white font-bold">Session stays active</p>
                    <p class="text-sm text-gray-400 mt-1">You will remain logged in after a successful password update.</p>
                </div>
            </div>
        </aside>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPassword = document.getElementById('current_password');
        const form = document.querySelector('form');

        if (currentPassword) {
            currentPassword.focus();
        }

        form.addEventListener('submit', function(event) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                event.preventDefault();
                alert('New password and confirmation do not match.');
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
