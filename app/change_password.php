<?php
require_once __DIR__ . '/../includes/header.php';

$csrfToken = $auth->generateCSRFToken();
?>

<div class="p-4 lg:p-6">
    <div class="max-w-3xl mx-auto">
        <div class="glass-card p-5 lg:p-7 mb-6">
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <p class="text-sm font-semibold text-green-400 mb-2">Account Security</p>
                    <h2 class="text-2xl font-bold text-white">Change Admin Password</h2>
                    <p class="text-gray-400 mt-2">Update the password for <?= htmlspecialchars($currentUser['username'] ?? 'your admin account', ENT_QUOTES, 'UTF-8') ?>.</p>
                </div>
                <div class="hidden sm:flex w-12 h-12 rounded-2xl bg-green-500 bg-opacity-10 items-center justify-center">
                    <i class="fas fa-key text-green-400"></i>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-500 bg-opacity-10 border border-green-400 border-opacity-30 text-green-100 p-4 mb-5 rounded-xl" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-500 bg-opacity-10 border border-red-400 border-opacity-30 text-red-100 p-4 mb-5 rounded-xl" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="app/backend/change_password_backend.php" class="space-y-5" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label for="current_password" class="block text-sm font-semibold text-gray-200 mb-2">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                        class="w-full px-4 py-3 bg-gray-900 bg-opacity-60 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20"
                        placeholder="Enter current password">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="new_password" class="block text-sm font-semibold text-gray-200 mb-2">New Password</label>
                        <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8"
                            class="w-full px-4 py-3 bg-gray-900 bg-opacity-60 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20"
                            placeholder="Minimum 8 characters">
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-semibold text-gray-200 mb-2">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" minlength="8"
                            class="w-full px-4 py-3 bg-gray-900 bg-opacity-60 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20"
                            placeholder="Repeat new password">
                    </div>
                </div>

                <div class="bg-blue-500 bg-opacity-10 border border-blue-400 border-opacity-20 rounded-xl p-4 text-sm text-blue-100">
                    <i class="fas fa-info-circle mr-2"></i>
                    Use a strong password. You will keep your current session after a successful change.
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl font-semibold transition-colors">
                        <i class="fas fa-save mr-2"></i>Update Password
                    </button>
                    <a href="dashboard" class="flex-1 text-center bg-white bg-opacity-5 hover:bg-opacity-10 text-gray-200 py-3 rounded-xl font-semibold transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
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
