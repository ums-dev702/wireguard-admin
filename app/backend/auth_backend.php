<?php
include_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../autoloader.php';
// Check if installation is complete
$db = new \WireGuardAdmin\Database();
$auth = new \WireGuardAdmin\Auth($db, SESSION_TIMEOUT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);

    // Basic rate limiting (simple implementation)
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastAttempt = $_SESSION['last_attempt'] ?? 0;

    if ($attempts >= MAX_LOGIN_ATTEMPTS && (time() - $lastAttempt) < 300) {
        header('Location: ../../login?error=Too many failed attempts. Please try again in 5 minutes.');
    } else {
        if ($auth->login($username, $password, $rememberMe)) {
            // Reset attempts on successful login
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_attempt']);

            header('Location: ../../dashboard?success=Login successful');
            exit;
        } else {
            $_SESSION['login_attempts'] = $attempts + 1;
            $_SESSION['last_attempt'] = time();
                header('Location: ../../login?error=Invalid username or password.');
        }
    }
}
?>