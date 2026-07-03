<?php
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../autoloader.php';

$db = new \WireGuardAdmin\Database();
$auth = new \WireGuardAdmin\Auth($db, SESSION_TIMEOUT);

$auth->requireAuth('../../login?error=Please login to change your password');

function redirect_change_password($type, $message) {
    header('Location: ../../change_password?' . $type . '=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_change_password('error', 'Invalid request method.');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!$auth->validateCSRFToken($csrfToken)) {
    redirect_change_password('error', 'Security token expired. Please try again.');
}

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$currentUser = $auth->getCurrentUser();

if (!$currentUser || empty($currentUser['id'])) {
    redirect_change_password('error', 'Unable to find the current admin account.');
}

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    redirect_change_password('error', 'All password fields are required.');
}

if (strlen($newPassword) < 8) {
    redirect_change_password('error', 'New password must be at least 8 characters long.');
}

if ($newPassword !== $confirmPassword) {
    redirect_change_password('error', 'New password and confirmation do not match.');
}

if (!$auth->verifyPassword($currentUser['id'], $currentPassword)) {
    redirect_change_password('error', 'Current password is incorrect.');
}

if (!$auth->changePassword($currentUser['id'], $newPassword)) {
    redirect_change_password('error', 'Password could not be updated. Please try again.');
}

unset($_SESSION['csrf_token']);
redirect_change_password('success', 'Password updated successfully.');
