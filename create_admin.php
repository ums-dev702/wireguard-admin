<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This admin setup script can only be run from the command line.";
    exit(1);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';

function admin_script_option(array $options, string $key, ?string $envKey = null, ?string $default = null): ?string
{
    if (isset($options[$key]) && $options[$key] !== false) {
        return (string) $options[$key];
    }

    if ($envKey !== null) {
        $value = getenv($envKey);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    return $default;
}

$options = getopt('', [
    'username::',
    'password::',
    'email::',
    'role::',
    'help'
]);

if (isset($options['help'])) {
    echo "Create or reset an admin login.\n\n";
    echo "Usage:\n";
    echo "  php create_admin.php --username=admin --password=StrongPassword123 --email=admin@example.com\n\n";
    echo "Environment variables are also supported: WG_ADMIN_USER, WG_ADMIN_PASS, WG_ADMIN_EMAIL.\n";
    echo "If no password is provided, a strong password is generated and printed once.\n";
    exit(0);
}

$username = trim(admin_script_option($options, 'username', 'WG_ADMIN_USER', 'admin'));
$password = admin_script_option($options, 'password', 'WG_ADMIN_PASS');
$email = trim(admin_script_option($options, 'email', 'WG_ADMIN_EMAIL', 'admin@example.com'));
$role = trim(admin_script_option($options, 'role', null, 'admin'));
$generatedPassword = false;

if ($username === '') {
    fwrite(STDERR, "Username cannot be empty.\n");
    exit(1);
}

if (!in_array($role, ['admin', 'user'], true)) {
    fwrite(STDERR, "Role must be admin or user.\n");
    exit(1);
}

if ($password === null || $password === '') {
    $password = bin2hex(random_bytes(12));
    $generatedPassword = true;
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters long.\n");
    exit(1);
}

try {
    $db = new \WireGuardAdmin\Database();
    $pdo = $db->getConnection();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $existing = $db->selectOne('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);

    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET password = ?, email = ?, role = ?, status = 'active', is_active = 1
             WHERE id = ?"
        );
        $stmt->execute([$passwordHash, $email, $role, $existing['id']]);
        $action = 'updated';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, password, email, role, status, is_active)
             VALUES (?, ?, ?, ?, 'active', 1)"
        );
        $stmt->execute([$username, $passwordHash, $email, $role]);
        $action = 'created';
    }

    echo "Admin login {$action} successfully.\n";
    echo "Username: {$username}\n";
    echo "Email: {$email}\n";
    echo "Role: {$role}\n";

    if ($generatedPassword) {
        echo "Generated password: {$password}\n";
        echo "Store this password now. It will not be shown again.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Failed to create admin login: " . $e->getMessage() . "\n");
    exit(1);
}
