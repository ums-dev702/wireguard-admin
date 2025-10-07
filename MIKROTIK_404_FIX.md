# 🔧 **MikroTik Script 404 Error Fix**

## ❌ **Issue Identified**
```
Fetching MikroTik script from: backend/generate_mikrotik_script.php?peer_id=3&interface=wg_wgtest
Response status: 404
Error: nginx/1.24.0 (Ubuntu) - 404 Not Found
```

**Root Cause**: The JavaScript is using incorrect relative paths that don't work with the current server configuration (nginx).

## ✅ **Fix Applied**

### **Enhanced URL Path Resolution**
Updated both functions in `app/wg-peers.php` to use dynamic path calculation:

#### **1. Preview Function (`previewMikroTikScript`)**
```javascript
// Build absolute URL for better compatibility
const currentPath = window.location.pathname;
const baseDir = currentPath.substring(0, currentPath.lastIndexOf('/')) + '/';
const url = `${baseDir}backend/generate_mikrotik_script.php?peer_id=${peerId}&interface=${encodeURIComponent(currentInterface)}`;
console.log('Current path:', currentPath);
console.log('Base directory:', baseDir);
console.log('Fetching MikroTik script from:', url);
```

#### **2. Download Function (`generateMikroTikScript`)**
```javascript
// Generate and download MikroTik RouterOS script
const currentPath = window.location.pathname;
const baseDir = currentPath.substring(0, currentPath.lastIndexOf('/')) + '/';
const url = `${baseDir}backend/generate_mikrotik_script.php?peer_id=${peerId}&interface=${encodeURIComponent(currentInterface)}`;
```

### **How the Fix Works**

1. **Dynamic Path Detection**: Gets the current page path using `window.location.pathname`
2. **Base Directory Calculation**: Extracts the directory containing the current page
3. **Relative Path Building**: Appends `backend/generate_mikrotik_script.php` to the base directory
4. **Enhanced Logging**: Shows the calculated paths in console for debugging

### **Example Path Resolution**

If you're accessing:
- **URL**: `https://yourdomain.com/app/wg-peers.php`
- **Current Path**: `/app/wg-peers.php`
- **Base Directory**: `/app/`
- **Final URL**: `/app/backend/generate_mikrotik_script.php`

If you're accessing:
- **URL**: `https://yourdomain.com/Wirgaurd_Admin/app/wg-peers.php`
- **Current Path**: `/Wirgaurd_Admin/app/wg-peers.php`
- **Base Directory**: `/Wirgaurd_Admin/app/`
- **Final URL**: `/Wirgaurd_Admin/app/backend/generate_mikrotik_script.php`

## 🧪 **Testing Tools Created**

### **1. URL Path Debugger** (`test_url_paths.html`)
- Shows current URL information
- Tests different path combinations
- Helps identify the correct URL structure
- **Usage**: Open in browser from the same location as your app

### **2. Enhanced Console Logging**
The fix now includes detailed console logging:
```
Current path: /app/wg-peers.php
Base directory: /app/
Fetching MikroTik script from: /app/backend/generate_mikrotik_script.php?peer_id=3&interface=wg_wgtest
```

## 🔍 **Troubleshooting Steps**

### **1. Check Browser Console**
1. Open DevTools (F12)
2. Go to Console tab
3. Look for the new logging messages showing the calculated paths

### **2. Test URL Manually**
Try accessing the script directly in browser:
```
https://yourdomain.com/app/backend/generate_mikrotik_script.php?peer_id=1&interface=wg0
```

### **3. Use the URL Path Debugger**
1. Open `test_url_paths.html` in your browser
2. Click the different URL options to test which one works
3. Use the working pattern in your main application

### **4. Check Server Configuration**
If still getting 404, verify:
- File exists at `/app/backend/generate_mikrotik_script.php`
- Nginx has proper permissions to access the file
- PHP is properly configured to handle .php files

## 🚀 **Alternative URL Patterns**

If the dynamic path calculation doesn't work, try these static alternatives:

### **Option A: Absolute Path**
```javascript
const url = `/app/backend/generate_mikrotik_script.php?peer_id=${peerId}&interface=${encodeURIComponent(currentInterface)}`;
```

### **Option B: From Document Root**
```javascript
const url = `app/backend/generate_mikrotik_script.php?peer_id=${peerId}&interface=${encodeURIComponent(currentInterface)}`;
```

### **Option C: Full Domain URL**
```javascript
const url = `${window.location.origin}/app/backend/generate_mikrotik_script.php?peer_id=${peerId}&interface=${encodeURIComponent(currentInterface)}`;
```

## ✅ **Expected Result**

After applying this fix, you should see in the browser console:
```
Current path: /your/path/wg-peers.php
Base directory: /your/path/
Fetching MikroTik script from: /your/path/backend/generate_mikrotik_script.php?peer_id=3&interface=wg_wgtest
Response status: 200
Script loaded successfully, length: XXXX
```

The MikroTik script preview should now load successfully! 🎉