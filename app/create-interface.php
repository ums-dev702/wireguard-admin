<?php
// Database connection helper
function get_db()
{
  $db = new PDO('sqlite:' . __DIR__ . '/db/wireguard_admin.db');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $db;
}

// Ensure interfaces table exists
function ensure_interfaces_table()
{
  $db = get_db();
  $db->exec('CREATE TABLE IF NOT EXISTS interfaces (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    iface_id TEXT UNIQUE,
    name TEXT NOT NULL,
    address TEXT,
    port INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )');
}
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

if (!is_authenticated()) {
  header('Location: /login.php');
  exit;
}

$success = '';
$error = '';

// Helper: Find a free UDP port (Linux)
function find_free_port($start = 20000, $end = 60000)
{
  for ($port = $start; $port <= $end; $port++) {
    $output = shell_exec("ss -lun | awk '{print \$5}' | grep -w ':$port' | wc -l");

    // Handle null safely
    $count = $output !== null ? trim($output) : '0';

    if ($count === '0') {
      return $port;
    }
  }
  return false;
}

// Initialize variables for all cases
$iface = '';
$private_key = defined('WG_PRIVATE_KEY') ? WG_PRIVATE_KEY : '';
$dns = '';
// Generate random address and free port by default
$rand = rand(2, 254);
$address = "10.0.0.$rand/24";
$listen_port = find_free_port();
if (!$listen_port) $listen_port = 51820;

// If POST, override with submitted values
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $iface = trim((string)($_POST['iface'] ?? ''));
  $private_key = trim((string)($_POST['private_key'] ?? $private_key));
  $listen_port = trim((string)($_POST['listen_port'] ?? $listen_port));
  $address = trim((string)($_POST['address'] ?? $address));


  // Handle address/port generation buttons
  if (isset($_POST['generate_address'])) {
    $rand = rand(2, 254);
    $address = "10.0.0.$rand/24";
    $success = "Address $address generated.";
  }
  if (isset($_POST['generate_port'])) {
    $free_port = find_free_port();
    if ($free_port) {
      $listen_port = $free_port;
      $success = "Free port $free_port generated.";
    } else {
      $error = "No free port found in range.";
    }
  }

  // Main create logic
  if (isset($_POST['create_interface'])) {
    if (strlen($iface) > 8) {
      $error = "Interface name must not exceed 8 characters.";
    }
    if ($iface && $private_key && $address) {
      if (empty($error)) {
        $conf = "[Interface]\n";
        $conf .= "PrivateKey = $private_key\n";
        $conf .= "Address = $address\n";
        $conf .= "ListenPort = $listen_port\n";
        $conf .= "SaveConfig = true\n\n";
        $conf .= "PostUp = ufw route allow in on wg0 out on eth0\n";
        $conf .= "PostUp = iptables -t nat -I POSTROUTING -o eth0 -j MASQUERADE\n";
        $conf .= "PreDown = ufw route delete allow in on wg0 out on eth0\n";
        $conf .= "PreDown = iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE\n";
        $conf_path = "/etc/wireguard/wg_$iface.conf";
        if (file_exists($conf_path)) {
          $error = "Interface configuration already exists.";
        } else {
          if (file_put_contents($conf_path, $conf) !== false) {

            //genrate a rendom  inderface id previx IWG
            $iface_id = "IWG" . rand(10000, 99999);
            // Bring up the interface
            $output = shell_exec("sudo wg-quick up $iface 2>&1");
            // Add to database
            try {
              ensure_interfaces_table();
              $db = get_db();
              $stmt = $db->prepare('INSERT INTO interfaces (iface_id, name, address, port) VALUES (?, ?, ?, ?)');
              $stmt->execute([$iface_id, $iface, $address, $listen_port]);
              $iface_id = $db->lastInsertId();
              $success = "WireGuard interface '$iface' (ID: $iface_id) created, started, and saved to database.";
            } catch (Exception $e) {
              $success = "WireGuard interface '$iface' created and started, but failed to save to database: " . $e->getMessage();
            }
          } else {
            $error = "Failed to write configuration file.";
          }
        }
      }
    } else {
      if (empty($error)) $error = "All required fields must be filled.";
    }
  }
}
?>
<style>
  .gradient-bg {
    background: linear-gradient(135deg, #000000 0%, #1a365d 100%);
  }

  .glass-effect {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .fade-in {
    animation: fadeIn 0.8s ease-in;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(30px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .form-label {
    color: #fff;
    font-weight: 500;
  }

  .form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    border: 1px solid #ccc;
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    margin-bottom: 1rem;
  }

  .form-input:focus {
    outline: none;
    border-color: #10b981;
    background: rgba(255, 255, 255, 0.25);
  }

  .btn-submit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    border: none;
    border-radius: 0.75rem;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.2s;
  }

  .btn-submit:hover {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    transform: translateY(-2px);
  }

  .alert {
    padding: 1rem;
    border-radius: 0.75rem;
    margin-bottom: 1rem;
  }

  .alert-success {
    background: #d1fae5;
    color: #065f46;
  }

  .alert-error {
    background: #fee2e2;
    color: #991b1b;
  }

  @media (max-width: 600px) {
    .glass-effect {
      padding: 1rem;
    }
  }
</style>
<div class="glass-effect rounded-2xl p-8 backdrop-blur-lg">
  <h1 class="text-2xl font-bold text-white mb-8 text-center"><i class="fas fa-plus-circle mr-2"></i>WireGuard Interfaces</h1>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php elseif ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <div class="text-center mb-8">
    <button class="btn-submit" onclick="document.getElementById('createModal').style.display='block'">
      <i class="fas fa-plus mr-2"></i>Create WireGuard Interface
    </button>
  </div>
  <!-- Modal for create/edit -->
  <div id="createModal" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
    <div style="background:#222;border-radius:1rem;padding:2rem;max-width:400px;margin:auto;position:relative;">
      <button onclick="document.getElementById('createModal').style.display='none'" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:#fff;font-size:1.5rem;">&times;</button>
      <form method="POST" autocomplete="off">
        <label class="form-label" for="iface">Interface Name <small style="color:#fbbf24;">Max 8 characters</small></label>
        <input class="form-input" type="text" id="iface" name="iface" required placeholder="mywg" maxlength="8" oninput="if(this.value.length>8)this.value=this.value.slice(0,8);" value="<?php echo htmlspecialchars($iface ?? '', ENT_QUOTES); ?>">
        <label class="form-label" for="private_key">Private Key</label>
        <div style="position:relative;">
          <input class="form-input" type="password" id="private_key" name="private_key" value="<?php echo WG_PRIVATE_KEY; ?>" readonly style="padding-right:2.5rem;">
          <button type="button" id="togglePrivateKey" style="position:absolute;top:50%;right:0.75rem;transform:translateY(-50%);background:none;border:none;outline:none;cursor:pointer;" tabindex="-1">
            <i id="privateKeyIcon" class="fas fa-eye" style="color:#10b981;"></i>
          </button>
        </div>
        <label class="form-label" for="address">Address (e.g., 10.0.0.1/24)</label>
        <div style="display:flex;gap:0.5rem;align-items:center;">
          <input class="form-input" type="text" id="address" name="address" required placeholder="10.0.0.1/24" value="<?php echo htmlspecialchars($address ?? '', ENT_QUOTES); ?>">
        </div>
        <label class="form-label" for="listen_port">Listen Port (default: 51820)</label>
        <div style="display:flex;gap:0.5rem;align-items:center;">
          <input class="form-input" type="number" id="listen_port" name="listen_port" placeholder="51820" value="<?php echo htmlspecialchars($listen_port ?? '', ENT_QUOTES); ?>">
        </div>
        <button class="btn-submit w-full mt-4" type="submit" name="create_interface"><i class="fas fa-plus mr-2"></i>Create Interface</button>
      </form>
    </div>
    <script>
      // Modal close on background click
      document.getElementById('createModal').onclick = function(e) {
        if (e.target === this) this.style.display = 'none';
      };
      // Eye toggle
      const privateKeyInput = document.getElementById('private_key');
      const toggleBtn = document.getElementById('togglePrivateKey');
      const icon = document.getElementById('privateKeyIcon');
      if (privateKeyInput && toggleBtn && icon) {
        toggleBtn.addEventListener('click', function(e) {
          e.preventDefault();
          if (privateKeyInput.type === 'password') {
            privateKeyInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
          } else {
            privateKeyInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
          }
        });
      }
    </script>
  </div>

  <!-- Table of interfaces -->
  <div class="mt-10">
    <h2 class="text-xl font-bold text-white mb-4">All Interfaces</h2>
    <table style="width:100%;background:rgba(0,0,0,0.3);color:#fff;border-radius:1rem;overflow:hidden;">
      <thead>
        <tr style="background:rgba(16,185,129,0.2);">
          <th style="padding:0.75rem;">ID</th>
          <th style="padding:0.75rem;">Name</th>
          <th style="padding:0.75rem;">Address</th>
          <th style="padding:0.75rem;">Port</th>
          <th style="padding:0.75rem;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        try {
          ensure_interfaces_table();
          $db = get_db();
          $rows = $db->query('SELECT * FROM interfaces ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
          foreach ($rows as $row): ?>
            <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
              <td style="padding:0.5rem;">#<?= htmlspecialchars($row['id']) ?></td>
              <td style="padding:0.5rem;"><?= htmlspecialchars($row['name']) ?></td>
              <td style="padding:0.5rem;"><?= htmlspecialchars($row['address']) ?></td>
              <td style="padding:0.5rem;"><?= htmlspecialchars($row['port']) ?></td>
              <td style="padding:0.5rem;">
                <button class="btn-submit" style="padding:0.25rem 0.75rem;font-size:0.9rem;" onclick="alert('Edit modal coming soon')"><i class="fas fa-edit"></i></button>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this interface?');">
                  <input type="hidden" name="delete_id" value="<?= htmlspecialchars($row['id']) ?>">
                  <button class="btn-submit" style="padding:0.25rem 0.75rem;font-size:0.9rem;background:#dc2626;" type="submit"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach;
        } catch (Exception $e) {
          echo '<tr><td colspan="5">Error loading interfaces: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
        }
        ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>