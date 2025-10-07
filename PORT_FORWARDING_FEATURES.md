# 🚀 **Enhanced WireGuard Admin - Complete Port Forwarding Solution**

## ✅ **Major New Features Implemented**

### **1. Advanced Port Forwarding Manager**
- **🌐 New Interface**: Dedicated port forwarding management at `app/port_forwarding.php`
- **🎯 Easy Access**: Direct link in main peers interface (orange "Port Forwarding" button)
- **🔧 Multi-Service Support**: Configure forwarding for multiple services per peer

### **2. Enhanced MikroTik Script Generation**
The MikroTik scripts now include comprehensive port forwarding examples:

#### **Your Specific iptables Rules Included:**
```bash
# Winbox Access (8291) via port 6843
iptables -t nat -A PREROUTING -p tcp --dport 6843 -j DNAT --to-destination 10.20.20.4:8291
iptables -t nat -A POSTROUTING -p tcp -d 10.20.20.4 --dport 8291 -j MASQUERADE
iptables -A FORWARD -p tcp -d 10.20.20.4 --dport 8291 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -A FORWARD -p tcp -s 10.20.20.4 --sport 8291 -m state --state ESTABLISHED,RELATED -j ACCEPT
```

#### **Additional Service Examples:**
- **Web Config (80)** via port 6842
- **HTTPS Config (443)** via port 6844
- **SSH Access (22)** via port 6845
- **Custom Service (8080)** via port 6846

### **3. Port Forwarding Manager Features**

#### **🎛️ Interactive Configuration:**
- **Peer Selection**: Choose any peer from dropdown
- **Service Templates**: Pre-configured common services
- **Custom Rules**: Add unlimited custom port mappings
- **Protocol Support**: TCP/UDP selection
- **Dynamic Management**: Add/remove rules easily

#### **📋 Default Service Templates:**
| Service | External Port | Internal Port | Protocol | Description |
|---------|---------------|---------------|----------|-------------|
| Winbox | 6843 | 8291 | TCP | MikroTik Winbox management |
| Web Config | 6842 | 80 | TCP | HTTP web interface |
| HTTPS Config | 6844 | 443 | TCP | HTTPS web interface |
| SSH Access | 6845 | 22 | TCP | SSH remote access |
| Custom Service | 6846 | 8080 | TCP | Custom application |

#### **🔧 Script Generation Features:**
- **Live Preview**: See generated rules before applying
- **Download Scripts**: Get complete bash scripts for setup
- **UFW Integration**: Automatic firewall rule generation
- **Persistence**: iptables-persistent configuration
- **Removal Scripts**: Automatic cleanup script generation

### **4. Complete Workflow**

#### **For Setting Up Port Forwarding:**
1. **Access Manager**: Click "Port Forwarding" button in main interface
2. **Select Peer**: Choose the target peer (e.g., MikroTik router)
3. **Configure Rules**: Modify default rules or add custom ones
4. **Generate Rules**: Preview the iptables commands
5. **Download Script**: Get a complete setup script
6. **Apply on VPS**: Run the script on your VPS

#### **Generated Script Includes:**
- ✅ IP forwarding enablement
- ✅ All iptables rules for each service
- ✅ UFW firewall configuration
- ✅ iptables-persistent setup
- ✅ Automatic removal script generation
- ✅ Verification commands

### **5. Enhanced MikroTik Integration**

The MikroTik scripts now show:
```routeros
:put "================== PORT FORWARDING EXAMPLES =================="
:put "# 1. Winbox Access (8291) via port 6843"
:put "iptables -t nat -A PREROUTING -p tcp --dport 6843 -j DNAT --to-destination 10.20.20.4:8291"
# ... complete rules for each service
```

### **6. Example Port Forwarding Setup**

#### **For a MikroTik Router at 10.20.20.4:**
```bash
# Setup Script (auto-generated)
#!/bin/bash
echo "Setting up port forwarding for MikroTik (10.20.20.4)"

# Enable IP forwarding
sysctl -w net.ipv4.ip_forward=1

# Winbox Access
iptables -t nat -A PREROUTING -p tcp --dport 6843 -j DNAT --to-destination 10.20.20.4:8291
iptables -t nat -A POSTROUTING -p tcp -d 10.20.20.4 --dport 8291 -j MASQUERADE
iptables -A FORWARD -p tcp -d 10.20.20.4 --dport 8291 -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
iptables -A FORWARD -p tcp -s 10.20.20.4 --sport 8291 -m state --state ESTABLISHED,RELATED -j ACCEPT

# Web Config
iptables -t nat -A PREROUTING -p tcp --dport 6842 -j DNAT --to-destination 10.20.20.4:80
# ... additional rules

# UFW Configuration
ufw allow 6843/tcp
ufw allow 6842/tcp
ufw reload

# Make persistent
apt install iptables-persistent -y
netfilter-persistent save
```

## **Updated Interface Structure**

### **Main Peers Table:**
| Name | Public Key | Peer IP | Allowed IPs | Status | **MikroTik** | Created | Actions |
|------|------------|---------|-------------|--------|-------------|---------|---------|
| Router1 | ABC123... | 10.20.20.4 | 10.20.20.4/32 | Active | 👁️ 📥 | Oct 7 | 📄 📱 🗑️ |

### **Header Navigation:**
- **New Interface** (Green) - Create WireGuard interfaces
- **Port Forwarding** (Orange) - **NEW!** Manage port forwarding rules
- **Add Peer** (Blue) - Add new VPN peers

## **User Benefits**

### ✅ **Simplified Management**
- **One-Click Access**: Direct link to port forwarding manager
- **Visual Interface**: No need to remember iptables syntax
- **Template-Based**: Common services pre-configured

### ✅ **Professional Features**
- **Multiple Services**: Forward multiple ports per peer
- **Script Generation**: Complete, ready-to-run scripts
- **Error Prevention**: Validation and conflict checking
- **Cleanup Support**: Automatic removal scripts

### ✅ **Production Ready**
- **Persistent Rules**: Survives server reboots
- **Firewall Integration**: UFW rules included
- **Comprehensive Setup**: Complete configuration scripts
- **Documentation**: Built-in instructions and examples

## **Quick Start Guide**

1. **Access**: Click "Port Forwarding" in the main interface
2. **Select Peer**: Choose your MikroTik or other device
3. **Configure**: Modify ports as needed (Winbox: 6843→8291)
4. **Generate**: Click "Generate iptables Rules"
5. **Download**: Get the complete setup script
6. **Apply**: Run `sudo bash script_name.sh` on your VPS
7. **Connect**: Access Winbox via `your-vps-ip:6843`

## **Files Added/Enhanced**

### **New Files:**
- `app/port_forwarding.php` - Main port forwarding interface
- `app/backend/download_port_forwarding_script.php` - Script generator
- `test_port_forwarding.php` - Testing functionality

### **Enhanced Files:**
- `app/wg-peers.php` - Added port forwarding link
- `app/backend/generate_mikrotik_script.php` - Enhanced with multiple examples

🎉 **Your WireGuard Admin panel now provides a complete, professional port forwarding solution that makes it easy to expose multiple services through your VPN!** 🚀