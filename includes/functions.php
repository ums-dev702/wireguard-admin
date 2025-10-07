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
    $cmd = 'sudo wg show  wg_acs 2>&1';
    $output = shell_exec($cmd);
    // Log output for debugging (optional, comment out if not needed)
    // file_put_contents(__DIR__ . '/../logs/wg_status.log', date('Y-m-d H:i:s') . "\n$cmd\n$output\n\n", FILE_APPEND);
    if ($output === null || trim($output) === '') {
        return "Error: No output from WireGuard status command.";
    }
    return $output;
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


function ensure_peers_table()
{
    $db = get_db(); // assumes get_db() returns a PDO connected to MySQL/MariaDB

    try {
        $sql = "CREATE TABLE IF NOT EXISTS wg_peers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            peer_id VARCHAR(191) NOT NULL UNIQUE,
            name VARCHAR(255)  NULL,
            iface_id  VARCHAR(255)  NULL,
            public_key TEXT NULL,
            private_key TEXT NULL,
            allowed_ips TEXT NULL,
            status ENUM('active', 'inactive', 'unconfigured') DEFAULT 'unconfigured',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_handshake DATETIME NULL,
            rx_bytes BIGINT UNSIGNED DEFAULT 0,
            tx_bytes BIGINT UNSIGNED DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->exec($sql);
    } catch (PDOException $e) {
        // Log and rethrow or handle as appropriate
        error_log('Error creating wg_peers table: ' . $e->getMessage());
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

        // First try with status column
        try {
            $stmt = $db->prepare('SELECT DISTINCT name FROM interfaces WHERE status = ?');
            $stmt->execute(['active']);
            $db_interfaces = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (Exception $e) {
            // If status column doesn't exist, get all interfaces
            error_log("Status column not found, getting all interfaces: " . $e->getMessage());
            $stmt = $db->prepare('SELECT DISTINCT name FROM interfaces');
            $stmt->execute();
            $db_interfaces = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        }

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
 * Get a free private subnet gateway IP (x.x.x.1)
 *
 * Picks from 10.x, 172.16–31.x, or 192.168.x ranges.
 *
 * @param int $maxAttempts Max tries to find a free subnet
 * @param string $cidr Subnet mask to append (default /24)
 * @return string
 * @throws RuntimeException
 */
function get_free_private_subnet_address(int $maxAttempts = 200, string $cidr = '/24'): string
{
    $attempt = 0;

    do {
        $attempt++;

        // Pick a random private range
        $range = random_int(1, 3);

        if ($range === 1) {
            // 10.x.0.1
            $second = random_int(1, 254);
            $ip = "10.$second.0.1";
        } elseif ($range === 2) {
            // 172.16–31.x.1
            $second = random_int(16, 31);
            $third  = random_int(0, 255);
            $ip = "172.$second.$third.1";
        } else {
            // 192.168.x.1
            $third = random_int(0, 255);
            $ip = "192.168.$third.1";
        }

        // Check if IP is in use
        $inUse = false;
        try {
            $inUse = is_ip_in_use($ip); // implement this
        } catch (Throwable $e) {
            error_log("IP check failed for $ip: " . $e->getMessage());
            $inUse = true;
        }

        if (!$inUse) {
            return $ip . $cidr;
        }
    } while ($attempt < $maxAttempts);

    throw new RuntimeException("Could not find a free private subnet after $maxAttempts attempts.");
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
    try {
        // Run ufw status (no paging). Note: may require root privileges depending on system setup.
        $cmd = 'ufw status 2>/dev/null';
        $raw = @shell_exec($cmd);

        if ($raw === null || trim($raw) === '') {
            // If ufw is not installed, disabled or command not allowed, assume no rule exists.
            return false;
        }

        // Check if UFW is inactive
        if (strpos($raw, 'Status: inactive') !== false) {
            return false;
        }

        // Look for explicit "/udp" mentions first (most reliable)
        if (preg_match('/\b' . preg_quote((string)$port, '/') . '\/udp\b/i', $raw)) {
            return true;
        }

        // Some UFW outputs might show "Anywhere                   ALLOW IN    20000/udp" or the port in other columns,
        // so also try a broader search for the port number (word boundary to reduce false positives).
        if (preg_match('/\b' . preg_quote((string)$port, '/') . '\b/', $raw)) {
            // Additional check to make sure it's actually a port rule and not just the port number appearing elsewhere
            $lines = explode("\n", $raw);
            foreach ($lines as $line) {
                if (strpos($line, (string)$port) !== false && 
                    (strpos($line, 'ALLOW') !== false || strpos($line, 'DENY') !== false)) {
                    return true;
                }
            }
        }

        return false;
    } catch (Throwable $e) {
        // If UFW check fails, log the error and assume port is not in UFW
        error_log("Error checking UFW status for port $port: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a port is in use by port forwarding rules
 *
 * @param int $port Port number to check
 * @return bool True if port is used in port forwarding, false otherwise
 */
function is_port_in_port_forwarding(int $port): bool
{
    try {
        $output = shell_exec('sudo iptables -t nat -L PREROUTING -n --line-numbers 2>/dev/null');
        
        if ($output === null || trim($output) === '') {
            // If iptables command fails or returns empty, assume no rules exist
            // This might happen if iptables is not installed or user lacks permissions
            return false;
        }

        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            // Look for lines that contain port forwarding rules with the specified port
            // Example: "1    DNAT       tcp  --  0.0.0.0/0            0.0.0.0/0            tcp dpt:8080 to:10.0.0.5:80"
            if (preg_match('/dpt:' . preg_quote((string)$port, '/') . '\b/', $line)) {
                return true;
            }
            
            // Also check for --dport format
            if (preg_match('/--dport\s+' . preg_quote((string)$port, '/') . '\b/', $line)) {
                return true;
            }
        }
        
        return false;
    } catch (Throwable $e) {
        // If checking port forwarding fails, be conservative and assume port is in use
        error_log("Error checking port forwarding for port $port: " . $e->getMessage());
        return true;
    }
}

/**
 * Validate if a specific port can be used for WireGuard
 * Provides detailed feedback about why a port cannot be used
 *
 * @param int $port Port number to validate
 * @return array Array with 'valid' (bool) and 'message' (string) keys
 */
function validate_wireguard_port(int $port): array
{
    if ($port < 1 || $port > 65535) {
        return [
            'valid' => false,
            'message' => "Port $port is outside valid range (1-65535)"
        ];
    }

    // Check if port is bound by any process
    $escapedPort = (int)$port;
    $ssCmd = "ss -lun 2>/dev/null | awk '{print \$5}' | grep -w ':" . $escapedPort . "' | wc -l";
    $ssOutput = @shell_exec($ssCmd);
    $ssCount = 0;
    if ($ssOutput !== null) {
        $ssCount = (int) trim($ssOutput);
    }

    if ($ssCount > 0) {
        return [
            'valid' => false,
            'message' => "Port $port is already in use by another process"
        ];
    }

    // Check UFW rules
    try {
        if (is_port_in_ufw($port)) {
            return [
                'valid' => false,
                'message' => "Port $port is already configured in UFW firewall rules"
            ];
        }
    } catch (Throwable $e) {
        return [
            'valid' => false,
            'message' => "Cannot check UFW rules for port $port: " . $e->getMessage()
        ];
    }

    // Check port forwarding rules
    if (is_port_in_port_forwarding($port)) {
        return [
            'valid' => false,
            'message' => "Port $port is already used in port forwarding rules"
        ];
    }

    // Try to bind the port as final verification
    $sock = @stream_socket_server("udp://0.0.0.0:$port", $errno, $errstr, STREAM_SERVER_BIND);
    if ($sock === false) {
        return [
            'valid' => false,
            'message' => "Cannot bind to port $port: $errstr (Error: $errno)"
        ];
    }
    
    fclose($sock);
    
    return [
        'valid' => true,
        'message' => "Port $port is available for use"
    ];
}

/**
 * Comprehensive port availability check
 * Checks if port is free from: socket binding, UFW rules, and port forwarding
 *
 * @param int $port Port number to check
 * @return bool True if port is completely free, false if in use anywhere
 */
function is_port_completely_free(int $port): bool
{
    // 1) Check if port is bound by any process
    $escapedPort = (int)$port;
    $ssCmd = "ss -lun 2>/dev/null | awk '{print \$5}' | grep -w ':" . $escapedPort . "' | wc -l";
    $ssOutput = @shell_exec($ssCmd);
    $ssCount = 0;
    if ($ssOutput !== null) {
        $ssCount = (int) trim($ssOutput);
    }

    if ($ssCount > 0) {
        return false; // Port is bound
    }

    // 2) Check UFW rules
    try {
        if (is_port_in_ufw($port)) {
            return false; // Port exists in UFW rules
        }
    } catch (Throwable $e) {
        error_log("Error checking UFW for port $port: " . $e->getMessage());
        return false; // Be conservative if UFW check fails
    }

    // 3) Check port forwarding rules
    if (is_port_in_port_forwarding($port)) {
        return false; // Port is used in port forwarding
    }

    // 4) Try to bind the port as final verification
    $sock = @stream_socket_server("udp://0.0.0.0:$port", $errno, $errstr, STREAM_SERVER_BIND);
    if ($sock === false) {
        return false; // Cannot bind port
    }
    
    fclose($sock);
    return true; // Port is completely free
}

/**
 * Extract IP address from allowed IPs string (removes CIDR notation)
 *
 * @param string $allowed_ips The allowed IPs string (e.g., "10.0.0.2/32")
 * @return string The IP address without CIDR (e.g., "10.0.0.2") or "N/A" if invalid
 */
function extract_peer_ip(string $allowed_ips): string
{
    if (empty($allowed_ips) || trim($allowed_ips) === '') {
        return 'N/A';
    }
    
    // Split by comma in case there are multiple allowed IPs
    $ips = explode(',', $allowed_ips);
    $first_ip = trim($ips[0]);
    
    // Extract IP part (before the slash)
    $ip_parts = explode('/', $first_ip);
    $ip = trim($ip_parts[0]);
    
    // Validate the IP
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $ip;
    }
    
    return 'N/A';
}

/**
 * Find a free UDP port in the given range.
 * Enhanced version that checks UFW, port forwarding, and socket binding
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
        if (is_port_completely_free($port)) {
            return $port;
        }
    }

    // No free port found
    return false;
}
