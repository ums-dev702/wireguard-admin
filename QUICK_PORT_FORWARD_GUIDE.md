# Quick Port Forwarding Feature

## Overview

The Quick Port Forward feature allows you to instantly set up port forwarding for a single service directly from the WireGuard Peers Management page, without navigating to the full port forwarding manager.

## Features

✅ **One-Click Access** - "Forward" button next to each peer
✅ **Service Templates** - Pre-configured templates for common services
✅ **Real-Time Previews** - See exactly what will be configured
✅ **Instant Application** - Apply rules directly to the server
✅ **No Navigation** - Stay on the peers page

## How to Use

### Step 1: Locate the Forward Button

On the **WireGuard Peers Management** page, you'll see three buttons next to each peer:
- 🟢 **Forward** - Quick port forward (new!)
- 🟠 **Manage** - Full port forwarding manager
- 🔴 **Delete** - Remove peer

### Step 2: Click Forward

Click the **Forward** button next to the peer you want to configure.

### Step 3: Configure Port Forwarding

The Quick Port Forward modal will open with these options:

#### Service Template (Optional)
Choose from pre-configured templates:
- **MikroTik Winbox (8291)** - Remote MikroTik management
- **SSH Access (22)** - SSH remote access
- **Web Server HTTP (80)** - HTTP web interface
- **Web Server HTTPS (443)** - HTTPS web interface
- **Remote Desktop (3389)** - Windows RDP
- **MySQL Database (3306)** - MySQL access
- **Custom Service** - Configure your own

#### Required Fields

1. **Service Name** - Descriptive name (e.g., "Winbox Access")
2. **External Port** - Port on your VPS (what you'll connect to)
3. **Internal Port** - Port on the peer device (where service runs)
4. **Protocol** - TCP or UDP
5. **Description** - Optional details

#### Real-Time Previews

As you fill in the fields, you'll see:
- Access URL: `your-vps-ip:XXXX`
- Forwards to: `peer-ip:XXXX`
- Configuration summary

### Step 4: Apply

Click **Apply Port Forward** button. The system will:
- Validate port availability
- Create iptables rules
- Configure firewall
- Save to database
- Show confirmation

## Example: Setting up Winbox Access

**Goal:** Access MikroTik Winbox remotely

1. Click **Forward** next to your MikroTik peer
2. Select **MikroTik Winbox (8291)** from templates
3. The form auto-fills:
   - Service Name: MikroTik Winbox
   - External Port: 6545
   - Internal Port: 8291
   - Protocol: TCP
4. Click **Apply Port Forward**
5. Done! Access via `your-vps-ip:6545`

## Example: Custom Service

**Goal:** Forward port 9000 for a custom application

1. Click **Forward** next to peer
2. Select **Custom Service** from templates
3. Fill in:
   - Service Name: "My App"
   - External Port: 9000
   - Internal Port: 9000
   - Protocol: TCP
   - Description: "Custom application"
4. Click **Apply Port Forward**
5. Access via `your-vps-ip:9000`

## Quick Template Reference

| Template | External Port | Internal Port | Use Case |
|----------|---------------|---------------|----------|
| Winbox | 6545 | 8291 | MikroTik management |
| SSH | 2222 | 22 | Remote terminal |
| HTTP | 8080 | 80 | Web server |
| HTTPS | 8443 | 443 | Secure web server |
| RDP | 3389 | 3389 | Windows remote desktop |
| MySQL | 3306 | 3306 | Database access |

## Managing Multiple Rules

For a single peer:

1. **First Rule**: Use Quick Forward (Winbox on 6545)
2. **More Rules**: Click **Manage** button
3. View all active rules in the full manager
4. Add/remove multiple rules at once

## Quick Forward vs Manage

### Use Quick Forward When:
- ✅ Adding one service quickly
- ✅ Using common service templates
- ✅ Want to stay on peers page
- ✅ Need fast setup

### Use Manage When:
- ✅ Adding multiple services
- ✅ Viewing all active rules
- ✅ Removing existing rules
- ✅ Generating scripts
- ✅ Need detailed configuration

## What Happens Behind the Scenes

When you click "Apply Port Forward", the system:

1. **Creates iptables rules:**
   ```bash
   iptables -t nat -A PREROUTING -p tcp --dport 6545 -j DNAT --to-destination 10.8.0.2:8291
   iptables -t nat -A POSTROUTING -p tcp -d 10.8.0.2 --dport 8291 -j MASQUERADE
   iptables -A FORWARD -p tcp -d 10.8.0.2 --dport 8291 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
   iptables -A FORWARD -p tcp -s 10.8.0.2 --sport 8291 -m state --state ESTABLISHED,RELATED -j ACCEPT
   ```

2. **Opens firewall port:**
   ```bash
   ufw allow 6545/tcp
   ```

3. **Makes persistent:**
   ```bash
   netfilter-persistent save
   ```

4. **Saves to database** for management

## Success Confirmation

After successful application, you'll see:
- ✅ Success notification
- Detailed confirmation dialog with:
  - Service name
  - Access URL
  - Forward destination
  - Link to manage all rules

## Troubleshooting

### "Forward" Button Disabled
- **Cause:** Peer doesn't have a valid IP address
- **Solution:** Assign an IP to the peer first

### Port Already in Use
- **Cause:** Port is used by another service
- **Solution:** Choose a different external port

### No Response
- **Cause:** Service not running on peer
- **Solution:** Verify service is active on peer device

### Cannot Access After Setup
1. Check peer is connected: `sudo wg show`
2. Verify service runs on peer
3. Test peer connectivity: `ping <peer-ip>`
4. Check firewall allows port

## Tips

### Port Selection
- Use ports **above 1024** for external access
- Common ranges: 6000-7000 for services
- Avoid well-known ports (80, 443, 22)

### Security
- Don't expose sensitive services without authentication
- Use strong passwords on forwarded services
- Consider VPN-only access for critical services
- Monitor access logs

### Documentation
After setting up rules:
- Note external ports in your documentation
- Keep a port assignment spreadsheet
- Document which peer uses which ports

## Common Use Cases

### 1. Remote Router Management
```
Service: MikroTik Winbox
External: 6545
Internal: 8291
Access: your-vps-ip:6545
```

### 2. Web Application
```
Service: Web App
External: 8080
Internal: 80
Access: http://your-vps-ip:8080
```

### 3. SSH Access
```
Service: SSH
External: 2222
Internal: 22
Access: ssh -p 2222 user@your-vps-ip
```

### 4. Database Access
```
Service: MySQL
External: 3306
Internal: 3306
Access: mysql -h your-vps-ip -P 3306
```

## Integration with Full Manager

Rules created via Quick Forward appear in:
- Full Port Forwarding Manager
- Active Rules table
- Database records
- iptables configuration

You can manage them from either interface!

## Keyboard Shortcuts

- **ESC** - Close modal
- **ENTER** - Submit form (when focused)

## Summary

Quick Port Forward provides:
- ⚡ **Speed** - Set up port forwarding in seconds
- 🎯 **Simplicity** - Pre-configured templates
- 👁️ **Clarity** - Real-time previews
- 🔄 **Integration** - Works with full manager
- ✅ **Reliability** - Same backend as full manager

Perfect for quick setups while keeping the full manager available for complex configurations!

---

**Need more control?** Use the **Manage** button to access the full Port Forwarding Manager with batch operations and script generation.
