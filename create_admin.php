<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This password hash generator can only be run from the command line.";
    exit(1);
}

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
    'password::',
    'help'
]);

if (isset($options['help'])) {
    echo "Generate an encrypted admin password hash only.\n\n";
    echo "Usage:\n";
    echo "  php create_admin.php --password=StrongPassword123\n\n";
    echo "Environment variable supported: WG_ADMIN_PASS.\n";
    echo "If no password is provided, a strong password is generated and printed once.\n";
    echo "This script does not connect to or insert into the database.\n";
    exit(0);
}

$password = admin_script_option($options, 'password', 'WG_ADMIN_PASS');
$generatedPassword = false;

if ($password === null || $password === '') {
    $password = bin2hex(random_bytes(12));
    $generatedPassword = true;
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters long.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

if ($passwordHash === false) {
    fwrite(STDERR, "Failed to generate password hash.\n");
    exit(1);
}

echo "Encrypted admin password generated.\n";
echo "Password hash:\n";
echo $passwordHash . "\n";
echo "\nNo database changes were made.\n";

if ($generatedPassword) {
    echo "\nGenerated plain password:\n";
    echo $password . "\n";
    echo "Store this password now. It will not be shown again.\n";
}
