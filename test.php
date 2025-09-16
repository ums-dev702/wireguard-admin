<?php
require_once __DIR__ . '/../config.php';

function get_wg_status() {
    return shell_exec('sudo wg show ' . WG_IFACE);
}

function get_wg_peers() {
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

function add_wg_peer($allowed_ips) {
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

function remove_wg_peer($public_key) {
    $config = file_get_contents(WG_CONF_PATH);
    $escaped_key = preg_quote(trim($public_key), '/');
    $new_config = preg_replace("/\[Peer\][^\[]*?PublicKey = {$escaped_key}[^\[]*/s", '', $config);
    file_put_contents(WG_CONF_PATH, $new_config);
    shell_exec("sudo wg set " . WG_IFACE . " peer $public_key remove");
    return true;
}

function get_port_rules() {
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

function add_port_rule($ext_port, $int_ip, $int_port) {
    $commands = [
        "iptables -t nat -A PREROUTING -p tcp --dport $ext_port -j DNAT --to-destination $int_ip:$int_port",
        "iptables -A FORWARD -p tcp -d $int_ip --dport $int_port -j ACCEPT"
    ];
    
    foreach ($commands as $cmd) {
        shell_exec("sudo $cmd");
    }
    return true;
}

function remove_port_rule($rule_num) {
    shell_exec("sudo iptables -t nat -D PREROUTING $rule_num");
    return true;
}
?>