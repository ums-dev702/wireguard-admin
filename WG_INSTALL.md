# WireGuard-Installation-Guide

```bash
sudo apt update
sudo apt install wireguard php php-cli php-mysql
```

Generate the private key and save it to a file. The following command will generate a new private key and save it to /etc/wireguard/private.key:

```bash
wg genkey | sudo tee /etc/wireguard/private.key
sudo chmod go= /etc/wireguard/private.key
```
The aut put will be like this:

base64_encoded_private_key_goes_here

```bash
aMgUf7AXiIPGGkIxo28ZYDcSjXi3+HZowMe7qSdB6kE=
```

To view the private key, you can use the following command:

```bash
sudo cat /etc/wireguard/private.key
```

To generate the public key from the private key, you can use the wg pubkey command. This command reads the private key from the file and outputs the corresponding public key. You can save this public key to a file, such as /etc/wireguard/public.key, using the following command:

```bash
sudo cat /etc/wireguard/private.key | wg pubkey | sudo tee /etc/wireguard/public.key
```

The aut put will be like this:

base64_encoded_public_key_goes_here

```bash
jqQqfyCGHrNNk9fvMWFn+IwrgteHXV7UNoqOFHlChw4=
```

To view the public key, you can use the following command:

```bash
sudo cat /etc/wireguard/public.key
```

#### Configure WireGuard Access
```bash
# Edit sudoers file
sudo visudo -f /etc/sudoers.d/wireguard
```

Add the following content:
```bash
www-data ALL=(ALL) NOPASSWD: /usr/sbin/iptables, /usr/sbin/iptables-save, /usr/sbin/iptables-restore, /usr/bin/wg-quick up *, /usr/bin/wg-quick down *, /usr/bin/wg set *, /usr/bin/wg, /usr/bin/wg show, /usr/local/bin/wg-dump, /usr/sbin/ufw, /sbin/ip, /usr/bin/wg

```


check if iptables is in /usr/sbin/iptables
```bash
which iptables
```

check all the port fowardin in iptables
```bash
sudo iptables -S
```

#### UFW (Ubuntu/Debian)
```bash
# Install UFW
sudo apt install ufw -y

# Allow WireGuard (adjust port as configured)
sudo ufw allow 51820/udp

# Enable firewall
sudo ufw enable
```

```bash
sudo chmod 775 /etc/wireguard/ && sudo chown www-data:www-data /etc/wireguard/
```

#### Enable IP Forwarding
```bash
# Enable permanently
echo 'net.ipv4.ip_forward = 1' | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

php create_admin.php --username=admin --password=admin123 --email=admin@example.com