<?php

/**
 * Find a free IP in 10.0.0.0/24 by randomly generating an address
 * and checking if it's reachable. Returns the first unused address
 * found in the subnet as "10.0.0.x/24".
 *
 * Notes:
 * - This uses ICMP ping (requires `ping` available on system).
 * - Also attempts a quick TCP connection on common ports as fallback.
 * - May be blocked by firewalls or systems that drop pings.
 * - Safe-guards with a maximum attempts counter to avoid infinite loops.
 */

declare(strict_types=1);
// Database connection helper
function get_db()
{
    try {
        // Create PDO instance
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT, DB_USER, DB_PASS);
        // Set PDO error mode
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

function get_wg_status()
{
    $cmd = 'sudo wg show ' . WG_IFACE . ' 2>&1';
    $output = shell_exec($cmd);
    // Log output for debugging (optional, comment out if not needed)
    // file_put_contents(__DIR__ . '/../logs/wg_status.log', date('Y-m-d H:i:s') . "\n$cmd\n$output\n\n", FILE_APPEND);
    if ($output === null || trim($output) === '') {
        return "Error: No output from WireGuard status command.";
    }
    return $output;
}

function get_wg_peers()
{
    $output = shell_exec('sudo wg show ' . WG_IFACE . ' peers');
    $peers = explode("\n", trim($output));
    $result = [];

    foreach ($peers as $peer) {
        if (!empty($peer)) {
            $result[] = [
                'public_key' => $peer,
                'allowed_ips' => shell_exec("sudo wg show " . WG_IFACE . " allowed-ips $peer"),
                'transfer' => shell_exec("sudo wg show " . WG_IFACE . " transfer | grep $peer")
            ];
        }
    }

    return $result;
}

function add_wg_peer($allowed_ips)
{
    $private_key = shell_exec('wg genkey');
    $public_key = shell_exec("echo '$private_key' | wg pubkey");

    $config = "\n[Peer]\nPublicKey = $public_key\nAllowedIPs = $allowed_ips\n";
    file_put_contents(WG_CONF_PATH, $config, FILE_APPEND);

    shell_exec('sudo wg-quick down ' . WG_IFACE . ' && sudo wg-quick up ' . WG_IFACE);
    return [
        'private_key' => trim($private_key),
        'public_key' => trim($public_key)
    ];
}

function remove_wg_peer($public_key)
{
    $config = file_get_contents(WG_CONF_PATH);
    $escaped_key = preg_quote(trim($public_key), '/');
    $new_config = preg_replace("/\[Peer\][^\[]*?PublicKey = {$escaped_key}[^\[]*/s", '', $config);
    file_put_contents(WG_CONF_PATH, $new_config);
    shell_exec("sudo wg set " . WG_IFACE . " peer $public_key remove");
    return true;
}

function get_port_rules()
{
    $output = shell_exec('sudo iptables -t nat -L PREROUTING -n --line-numbers');
    $lines = explode("\n", $output);
    $rules = [];

    foreach ($lines as $line) {
        if (preg_match('/^(\d+).*dpt:(\d+).*to:([\d.]+):(\d+)/', $line, $matches)) {
            $rules[] = [
                'num' => $matches[1],
                'ext_port' => $matches[2],
                'int_ip' => $matches[3],
                'int_port' => $matches[4]
            ];
        }
    }

    return $rules;
}

function add_port_rule($ext_port, $int_ip, $int_port)
{
    $commands = [
        "iptables -t nat -A PREROUTING -p tcp --dport $ext_port -j DNAT --to-destination $int_ip:$int_port",
        "iptables -A FORWARD -p tcp -d $int_ip --dport $int_port -j ACCEPT"
    ];

    foreach ($commands as $cmd) {
        shell_exec("sudo $cmd");
    }
    return true;
}

function remove_port_rule($rule_num)
{
    shell_exec("sudo iptables -t nat -D PREROUTING $rule_num");
    return true;
}




// Ensure interfaces table exists
function ensure_interfaces_table()
{
    $db = get_db(); // assumes get_db() returns a PDO connected to MySQL/MariaDB

    try {
        $sql = "CREATE TABLE IF NOT EXISTS interfaces (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            iface_id VARCHAR(191) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            address TEXT NULL,
            port INT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->exec($sql);
    } catch (PDOException $e) {
        // Log and rethrow or handle as appropriate
        error_log('Error creating interfaces table: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Send error message to Telegram bot
 */
function sendToTelegram(string $message): void
{
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";

    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text'    => $message,
        'parse_mode' => 'HTML'
    ];

    // Use cURL for reliability
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Get WireGuard interfaces from database only
 * Adds "wg_" prefix to each interface name
 *
 * @return array Array of interface names
 */
function get_available_interfaces(): array
{
    $interfaces = [];

    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT DISTINCT name FROM interfaces WHERE status = ?');
        $stmt->execute(['active']);
        $db_interfaces = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        foreach ($db_interfaces as $db_interface) {
            if (!empty($db_interface)) {
                // Always prefix with wg_
                $interfaces[] = 'wg_' . $db_interface;
            }
        }
    } catch (Exception $e) {
        // If database query fails, just log it and continue
        error_log("Error fetching interfaces from database: " . $e->getMessage());
    }

    // Remove duplicates and sort
    $interfaces = array_unique($interfaces);
    sort($interfaces);

    return $interfaces;
}


/**
 * Check whether an IP address is currently in use.
 *
 * @param string $ip IPv4 address (e.g. "10.0.0.5")
 * @param float  $tcpTimeout seconds timeout for TCP fallback (default 0.5)
 * @return bool true if IP seems in use (responds to ping or TCP), false otherwise
 */
function is_ip_in_use(string $ip, float $tcpTimeout = 0.5): bool
{
    // Normalize IP (basic validation)
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        throw new InvalidArgumentException("Invalid IPv4 address: $ip");
    }

    $osFamily = PHP_OS_FAMILY; // "Windows", "Linux", "BSD", "Darwin", etc.

    // Try ICMP ping first (platform-aware)
    if ($osFamily === 'Windows') {
        // -n 1 : send 1 echo request
        // -w 1000 : timeout in milliseconds
        $cmd = sprintf('ping -n 1 -w 1000 %s', escapeshellarg($ip));
        exec($cmd, $output, $ret);
        // On Windows, return code 0 if ping successful
        if ($ret === 0) {
            return true;
        }
    } else {
        // Unix-like: -c 1 send 1, -W 1 or -w 1 depending; use -W (wait time in seconds)
        // For Mac/BSD, -W expects milliseconds so fallback to -c 1 -t 1 might be needed.
        // We'll try a common Linux style first, and if exec returns non-zero, we won't assume false immediately.
        $cmd = sprintf('ping -c 1 -W 1 %s 2>&1', escapeshellarg($ip));
        exec($cmd, $output, $ret);
        if ($ret === 0) {
            return true;
        }

        // On BSD/Mac the -W flag behaves differently; try a broad fallback (short timeout)
        if ($ret !== 0) {
            $cmd2 = sprintf('ping -c 1 -t 1 %s 2>&1', escapeshellarg($ip));
            exec($cmd2, $output2, $ret2);
            if ($ret2 === 0) {
                return true;
            }
        }
    }

    // If ICMP didn't show up (or is blocked), try quick TCP connect on common ports
    $ports = [80, 22, 443, 3389, 53]; // web, ssh, https, rdp, dns
    foreach ($ports as $port) {
        $fp = @fsockopen($ip, (int)$port, $errno, $errstr, $tcpTimeout);
        if ($fp !== false) {
            fclose($fp);
            return true;
        }
    }

    // Nothing responded -> assume IP not in use (may still be in use if host drops ping/tcp)
    return false;
}

/**
 * Generate a random free IP in 10.0.0.0/24
 *
 * @param int $min octet minimum (default 2)
 * @param int $max octet maximum (default 254)
 * @param int $maxAttempts cap on tries before giving up
 * @return string the address string like "10.0.0.123/24"
 * @throws RuntimeException if no free IP found within attempts
 */
function get_free_10_subnet_address(int $min = 2, int $max = 254, int $maxAttempts = 200): string
{
    if ($min < 1 || $max > 254 || $min >= $max) {
        throw new InvalidArgumentException('Invalid min/max octet range.');
    }

    $attempt = 0;
    do {
        $attempt++;
        // use random_int for cryptographic randomness (and to avoid predictable rand())
        $rand = random_int($min, $max);
        $ip = "10.0.0.$rand";

        // Check if IP is in use
        $inUse = false;
        try {
            $inUse = is_ip_in_use($ip);
        } catch (Throwable $e) {
            // If the ping command or validation fails, treat as "in use" to be safe, but continue
            // (you could also log $e->getMessage()).
            $inUse = true;
        }

        if (!$inUse) {
            return $ip . '/24';
        }

        // loop until free or attempts exhausted
    } while ($attempt < $maxAttempts);

    throw new RuntimeException("Could not find a free IP in 10.0.0.0/24 after $maxAttempts attempts.");
}


/**
 * Check if a UDP port appears in UFW rules.
 *
 * This inspects the output of `ufw status` and looks for lines mentioning "<port>/udp".
 * It is intentionally permissive to catch variations like "20000/udp" or "20000".
 *
 * @param int $port
 * @return bool True if port is present in UFW rules, false otherwise
 */
function is_port_in_ufw(int $port): bool
{
    // Run ufw status (no paging). Note: may require root privileges depending on system setup.
    // We avoid escape expansion issues by using escapeshellarg for the command's argument where needed.
    $cmd = 'ufw status';
    $output = null;
    $ret = null;

    // Use shell_exec to capture full output (safer for multi-line)
    $raw = @shell_exec($cmd . ' 2>/dev/null');

    if ($raw === null || trim($raw) === '') {
        // If ufw is not installed, disabled or command not allowed, assume no rule exists.
        // You could also decide to throw or log here.
        return false;
    }

    // Look for explicit "/udp" mentions first (most reliable)
    if (preg_match('/\b' . preg_quote((string)$port, '/') . '\/udp\b/i', $raw)) {
        return true;
    }

    // Some UFW outputs might show "Anywhere                   ALLOW IN    20000/udp" or the port in other columns,
    // so also try a broader search for the port number (word boundary to reduce false positives).
    if (preg_match('/\b' . preg_quote((string)$port, '/') . '\b/', $raw)) {
        // It's possible the port might be mentioned in a different context; further filtering could be added.
        return true;
    }

    return false;
}

/**
 * Find a free UDP port in the given range.
 *
 * @param int $start Starting port (inclusive)
 * @param int $end Ending port (inclusive)
 * @return int|false Returns the free port number or false if none found
 */
function find_free_udp_port(int $start = 20000, int $end = 60000)
{
    if ($start < 1 || $end > 65535 || $start > $end) {
        throw new InvalidArgumentException('Invalid port range.');
    }

    // We'll iterate sequentially. If you prefer random picks, replace loop with random_int picks + attempts cap.
    for ($port = $start; $port <= $end; $port++) {
        // 1) Quick check with ss to see if any process is bound to this port (UDP)
        // The original approach: ss -lun | awk '{print $5}' | grep -w ':$port' | wc -l
        // We'll keep similar but more defensive.
        $escapedPort = (int)$port;
        $ssCmd = "ss -lun 2>/dev/null | awk '{print \$5}' | grep -w ':" . $escapedPort . "' | wc -l";
        $ssOutput = @shell_exec($ssCmd);
        $ssCount = 0;
        if ($ssOutput !== null) {
            $ssCount = (int) trim($ssOutput);
        }

        if ($ssCount > 0) {
            // Port is in use at the socket level; skip it
            continue;
        }

        // 2) Check UFW rules for this port (so we don't accidentally pick a port that is already managed/opened)
        try {
            if (is_port_in_ufw($port)) {
                // Port exists in UFW rules; skip.
                continue;
            }
        } catch (Throwable $e) {
            // If checking UFW fails for any reason, be conservative and skip this port.
            // Optionally log $e->getMessage() if you have a logger.
            continue;
        }

        // 3) Double-check by trying to bind the UDP port locally
        // Use STREAM_SERVER_BIND to attempt binding. If successful, release and return port.
        $sock = @stream_socket_server("udp://0.0.0.0:$port", $errno, $errstr, STREAM_SERVER_BIND);
        if ($sock !== false) {
            // Successfully bound => port is free for us to use
            fclose($sock);
            return $port;
        }

        // otherwise, binding failed — port not usable, continue scanning
    }

    // No free port found
    return false;
}
