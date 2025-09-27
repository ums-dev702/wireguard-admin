<?php
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



// Helper: Find a free UDP port (Linux)
function find_free_port($start = 20000, $end = 60000)
{
    for ($port = $start; $port <= $end; $port++) {
        // First check with ss command
        $output = shell_exec("ss -lun | awk '{print \$5}' | grep -w ':$port' | wc -l");

        $count = $output !== null ? trim($output) : '0';

        if ($count === '0') {
            // Double-check by trying to bind the port
            $sock = @stream_socket_server("udp://0.0.0.0:$port", $errno, $errstr, STREAM_SERVER_BIND);
            if ($sock) {
                fclose($sock); // release immediately
                return $port;  // port is free
            }
        }
    }
    return false; // no free port found
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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->exec($sql);
    } catch (PDOException $e) {
        // Log and rethrow or handle as appropriate
        error_log('Error creating interfaces table: ' . $e->getMessage());
        throw $e;
    }
}
