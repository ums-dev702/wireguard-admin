# WireGuard Port Forwarding Management Guide

## Overview

This feature allows you to set up port forwarding rules for WireGuard peers, enabling external access to services running on devices connected through the VPN.

## Features

✅ **Web-Based Management** - Configure port forwarding through an intuitive web interface
✅ **Direct Application** - Apply iptables rules directly to the server with one click
✅ **Rule Templates** - Pre-configured templates for common services (Winbox, SSH, HTTP, HTTPS)
✅ **Script Generation** - Download bash scripts for manual installation
✅ **Active Rule Monitoring** - View all active port forwarding rules
✅ **Database Tracking** - All rules stored in database for easy management
✅ **Telegram Notifications** - Get notified when rules are added or removed

## How It Works

### Port Forwarding Basics

Port forwarding allows external traffic to reach services on your WireGuard peer devices:

```
Internet → VPS Server (External Port) → WireGuard Peer (Internal Port)
```

**Example:**
- External Port: 6545 on VPS
- Internal Port: 8291 on Peer (10.8.0.2)
- Result: Accessing `vps_ip:6545` connects to MikroTik Winbox on peer device

## Usage Guide

### Step 1: Access Port Forwarding Manager

1. Navigate to **WireGuard Peers Management**
2. Click the **Port Forwarding** button in the top navigation
   - Or click the **Ports** button next to any peer

### Step 2: Select a Peer

1. Choose the peer from the dropdown menu
2. The peer's IP address will be displayed
3. Existing rules for that peer will be shown (if any)

### Step 3: Configure Port Forwarding Rules

#### Using Pre-configured Templates

The system provides default rules for common services:

| Service | External Port | Internal Port | Protocol | Description |
|---------|---------------|---------------|----------|-------------|
| Winbox | 6843 | 8291 | TCP | MikroTik Winbox management |
| Web Config | 6842 | 80 | TCP | HTTP web interface |
| HTTPS Config | 6844 | 443 | TCP | HTTPS web interface |
| SSH Access | 6845 | 22 | TCP | SSH remote access |
| Custom Service | 6846 | 8080 | TCP | Custom application |

#### Adding Custom Rules

1. Click **+ Add Rule** to add more port forwarding rules
2. Fill in the details:
   - **Service Name**: Descriptive name (e.g., "Database Access")
   - **External Port**: Port on your VPS (1024-65535)
   - **Internal Port**: Port on peer device (1-65535)
   - **Protocol**: TCP or UDP
   - **Description**: Optional details about the service

### Step 4: Apply Port Forwarding Rules

You have three options:

#### Option 1: Apply Rules Directly (Recommended)

1. Click **Apply Rules to Server** button
2. Confirm the action
3. The system will:
   - Create iptables NAT rules
   - Add FORWARD chain rules
   - Configure UFW firewall
   - Make rules persistent
   - Save rules to database

#### Option 2: Generate and View Rules

1. Click **Generate iptables Rules**
2. View the generated commands
3. Copy or download for manual execution

#### Option 3: Download as Script

1. Click **Download Script**
2. Save the bash script
3. Upload to your server
4. Execute: `sudo bash port_forwarding_<peer_name>.sh`

## Technical Details

### iptables Commands Generated

For each port forwarding rule, the system creates 4 iptables rules:

```bash
# 1. DNAT rule - Redirect incoming traffic to peer
iptables -t nat -A PREROUTING -p tcp --dport <external_port> -j DNAT --to-destination <peer_ip>:<internal_port>

# 2. MASQUERADE rule - Enable NAT for the connection
iptables -t nat -A POSTROUTING -p tcp -d <peer_ip> --dport <internal_port> -j MASQUERADE

# 3. FORWARD rule (incoming) - Allow traffic to peer
iptables -A FORWARD -p tcp -d <peer_ip> --dport <internal_port> -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT

# 4. FORWARD rule (outgoing) - Allow responses from peer
iptables -A FORWARD -p tcp -s <peer_ip> --sport <internal_port> -m state --state ESTABLISHED,RELATED -j ACCEPT
```

### Firewall Configuration

UFW rules are automatically created:

```bash
sudo ufw allow <external_port>/tcp
sudo ufw reload
```

### Persistence

Rules are made persistent using:

```bash
sudo apt install iptables-persistent -y
sudo netfilter-persistent save
```

## Managing Active Rules

### Viewing Active Rules

When you select a peer, all active port forwarding rules are displayed in a table showing:
- Service name
- External and internal ports
- Protocol
- Description
- Creation date
- Actions

### Removing Rules

1. Locate the rule in the **Active Port Forwarding Rules** table
2. Click the **Remove** button
3. Confirm the removal
4. The system will:
   - Remove all associated iptables rules
   - Update firewall configuration
   - Delete from database
   - Send notification

## Example Scenarios

### Scenario 1: MikroTik Remote Management

**Goal:** Access MikroTik Winbox from anywhere

**Configuration:**
- Service: Winbox Access
- External Port: 6545
- Internal Port: 8291
- Protocol: TCP
- Peer IP: 10.8.0.2

**Result:** Connect to `your-vps-ip:6545` to access Winbox

### Scenario 2: Web Server Hosting

**Goal:** Host a website on a device behind WireGuard

**Configuration:**
- Service: Web Server
- External Port: 8080
- Internal Port: 80
- Protocol: TCP
- Peer IP: 10.8.0.5

**Result:** Access website at `your-vps-ip:8080`

### Scenario 3: Multiple Services on One Peer

You can forward multiple ports for a single peer:

1. SSH: External 2222 → Internal 22
2. HTTP: External 8080 → Internal 80
3. HTTPS: External 8443 → Internal 443
4. Custom App: External 9000 → Internal 9000

## Security Considerations

⚠️ **Important Security Notes:**

1. **Port Selection**
   - Use non-standard ports when possible
   - Avoid ports below 1024 for external access
   - Don't use ports already in use by other services

2. **Access Control**
   - Consider using VPN + port forwarding for sensitive services
   - Implement authentication on forwarded services
   - Use strong passwords

3. **Firewall Rules**
   - Rules allow traffic from ANY source
   - Consider adding source IP restrictions if needed
   - Monitor access logs regularly

4. **Service Security**
   - Keep forwarded services updated
   - Use HTTPS/TLS when possible
   - Implement rate limiting where applicable

## Troubleshooting

### Port Forwarding Not Working

1. **Check if rules are active:**
   ```bash
   sudo iptables -t nat -L PREROUTING -n --line-numbers
   ```

2. **Verify UFW allows the port:**
   ```bash
   sudo ufw status
   ```

3. **Test peer connectivity:**
   ```bash
   ping <peer_ip>
   ```

4. **Check if service is running on peer:**
   ```bash
   # On the peer device
   netstat -tlnp | grep <internal_port>
   ```

### Rules Not Persistent After Reboot

Install and configure iptables-persistent:
```bash
sudo apt install iptables-persistent -y
sudo netfilter-persistent save
```

### Port Already in Use

If you get "port already in use" error:

1. Check what's using the port:
   ```bash
   sudo ss -tlnp | grep :<port>
   ```

2. Choose a different external port
3. Or stop the conflicting service

## Database Schema

Port forwarding rules are stored in the `port_forwarding_rules` table:

```sql
CREATE TABLE port_forwarding_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    peer_id INT UNSIGNED NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    external_port INT NOT NULL,
    internal_port INT NOT NULL,
    protocol ENUM('tcp', 'udp', 'both') DEFAULT 'tcp',
    description TEXT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (peer_id) REFERENCES wg_peers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_external_port (external_port, protocol)
);
```

## API Reference

### Add Port Forwarding Rule

```javascript
POST /app/backend/port_forwarding_backend.php

action: add_port_forward
peer_id: <peer_id>
service_name: <service_name>
external_port: <external_port>
internal_port: <internal_port>
protocol: tcp|udp
description: <description>
```

### Remove Port Forwarding Rule

```javascript
POST /app/backend/port_forwarding_backend.php

action: remove_port_forward
rule_id: <rule_id>
```

### Get Port Rules

```javascript
POST /app/backend/port_forwarding_backend.php

action: get_port_rules
peer_id: <peer_id> (optional)
```

### Validate Port

```javascript
POST /app/backend/port_forwarding_backend.php

action: validate_port
port: <port_number>
protocol: tcp|udp
```

## Best Practices

1. **Plan Your Ports**
   - Document all port assignments
   - Use a consistent port range (e.g., 6000-7000)
   - Keep a spreadsheet of port mappings

2. **Test Before Production**
   - Test rules with non-critical services first
   - Verify connectivity from external networks
   - Monitor for issues

3. **Regular Audits**
   - Review active rules monthly
   - Remove unused rules
   - Update documentation

4. **Backup Configuration**
   - Export iptables rules: `sudo iptables-save > backup.rules`
   - Backup database regularly
   - Keep generated scripts

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review system logs: `/var/log/syslog`
3. Check WireGuard status: `sudo wg show`
4. Verify iptables rules: `sudo iptables -L -n -v`

## Credits

Port Forwarding Management System for WireGuard Admin Panel
