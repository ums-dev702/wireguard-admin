Private KEY: GLwz4p90zxmERx1Xcyvy3sK9g3DdKmAePUKN0ssPa00=

Public KEY: lKl6454znV4ABgqwWY9aNMWI/OWS2XfHO3iF91JS6SE=



```bash
sudo nano /etc/wireguard/wg0.conf
```

```bash
[Interface]
PrivateKey = GLwz4p90zxmERx1Xcyvy3sK9g3DdKmAePUKN0ssPa00=
Address = 172.7.0.1/24
ListenPort = 51820
SaveConfig = true

PostUp = ufw route allow in on wg0 out on eth0
PostUp = iptables -t nat -I POSTROUTING -o eth0 -j MASQUERADE
PreDown = ufw route delete allow in on wg0 out on eth0
PreDown = iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE
```


check if iptables is in /usr/sbin/iptables
```bash
which iptables
```


#### UFW (Ubuntu/Debian)
```bash
# Allow WireGuard (adjust port as configured)
sudo ufw allow 51820/udp
```

#### Enable IP Forwarding
```bash
# Enable permanently
echo 'net.ipv4.ip_forward = 1' | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```



After saving the configuration file, you can start the WireGuard interface using the wg-quick command:

```bash
sudo wg-quick up wg0
```

To check the status of the WireGuard interface, you can use the following command:

```bash
sudo wg show
```



# Mikrotik Configuration with WireGuard
To configure a Mikrotik router to connect to a WireGuard server, you will need to follow these steps:

1. **Generate Keys**: On your Mikrotik router, generate a private and public key pair for WireGuard. You can do this using the following commands in the Mikrotik terminal:

  ```bash
  # Create WireGuard interface
:if ([:len [/interface wireguard find where name="mkwg_my_wg"]] = 0) do={
    /interface wireguard add name="mkwg_my_wg" mtu=1420
}

# Assign client IP
:if ([:len [/ip address find where address="172.7.0.2/24"]] = 0) do={
    /ip address add address="172.7.0.2/24" interface="mkwg_my_wg"
}

# Add server peer
:if ([:len [/interface wireguard peers find where endpoint-address="109.205.181.102"]] = 0) do={
    /interface wireguard peers add \
        interface="mkwg_my_wg" \
        public-key="lKl6454znV4ABgqwWY9aNMWI/OWS2XfHO3iF91JS6SE=" \
        endpoint-address="109.205.181.102" \
        endpoint-port=51820 \
        allowed-address="172.7.0.0/24" \
        persistent-keepalive=30s
}

:put ""
:put "==================== WIREGUARD SETUP COMPLETED ===================="
:put "Interface: mkwg_my_wg"
:put "Client IP: 172.7.0.2/24"
:put "Server: 109.205.181.102:51820"
:put ("Client Public Key: " . [/interface wireguard get [find name="mkwg_my_wg"] value-name=public-key])
:put "==================================================================="
```


Put down wg-quick

```bash
sudo wg-quick down wg0
```


Then add the peer to the WireGuard server configuration:

```bash
sudo wg set wg0 peer base64_encoded_public_key_goes_here allowed-ips
```

eg

```bash
sudo wg set wg0 peer uKJVHa0NZoI9q3Jp6IHB4QrldrYftDMOW4db1+s5f2k= allowed-ips 172.7.0.2/32
```

or add manually to the configuration file:

```bash
sudo nano /etc/wireguard/wg0.conf
```

Add the following lines under the [Interface] section:

```bash
[Peer]
PublicKey = base64_encoded_public_key_goes_here
AllowedIPs = 172.7.0.2/32
```



# Winbox Port Forwarding Example

```bash
iptables -t nat -A PREROUTING -p tcp --dport 6843 -j DNAT --to-destination 10.20.20.4:8291
iptables -t nat -A POSTROUTING -p tcp -d 10.20.20.4 --dport 8291 -j MASQUERADE
iptables -A FORWARD -p tcp -d 10.20.20.4 --dport 8291 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -A FORWARD -p tcp -s 10.20.20.4 --sport 8291 -m state --state ESTABLISHED,RELATED -j ACCEPT
```

# WebConfig Port Forwarding Example

```bash
iptables -t nat -A PREROUTING -p tcp --dport 6842 -j DNAT --to-destination 10.20.20.4:80
iptables -t nat -A POSTROUTING -p tcp -d 10.20.20.4 --dport 80 -j MASQUERADE
iptables -A FORWARD -p tcp -d 10.20.20.4 --dport 80 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -A FORWARD -p tcp -s 10.20.20.4 --sport 80 -m state --state ESTABLISHED,RELATED -j ACCEPT
```

# Winbox Port Forwarding Removal Example

```bash
iptables -t nat -D PREROUTING -p tcp --dport 6843 -j DNAT --to-destination 10.20.20.4:8291
iptables -t nat -D POSTROUTING -p tcp -d 10.20.20.4 --dport 8291 -j MASQUERADE
iptables -D FORWARD -p tcp -d 10.20.20.4 --dport 8291 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -D FORWARD -p tcp -s 10.20.20.4 --sport 8291 -m state --state ESTABLISHED,RELATED -j ACCEPT
```

API Port Forwarding Example (Port 8728)

```bash
iptables -t nat -A PREROUTING -p tcp --dport 6844 -j DNAT --to-destination 172.7.0.2:8728
iptables -t nat -A POSTROUTING -p tcp -d 172.7.0.2 --dport 8728 -j MASQUERADE
iptables -A FORWARD -p tcp -d 172.7.0.2 --dport 8728 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -A FORWARD -p tcp -s 172.7.0.2 --sport 8728 -m state --state ESTABLISHED,RELATED -j ACCEPT
```

allow port 6844 to access the API from outside

```bash
sudo ufw allow 51820/udp comment 'WireGuard'
sudo ufw allow 6844/tcp comment 'MikroTik API'
sudo ufw reload
```

109.205.181.102:6844
