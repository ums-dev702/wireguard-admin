<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
if (!is_authenticated()) {
  header('Location: login');
  exit;
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

<div class="p-4 lg:p-6">
  <div class="glass-effect rounded-2xl p-8 backdrop-blur-lg">
    <h1 class="text-2xl font-bold text-white mb-8"><i class="fas fa-plus-circle mr-2"></i>WireGuard Interfaces</h1>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="text-right mb-8">
      <!-- Example button to open modal -->
      <button onclick="document.getElementById('createModal').style.display='flex'"
        style="margin:2rem;padding:0.7rem 1.2rem;border:none;border-radius:0.5rem;background:#10b981;color:#fff;cursor:pointer;">
        + New Interface
      </button>
    </div>


    <!-- Modal for create/edit -->
    <div id="createModal" class="modal" style="display:none;">
      <div class="modal-content glass-effect fade-in">
        <button class="close-btn" onclick="document.getElementById('createModal').style.display='none'">&times;</button>
        <h2 class="modal-title"><i class="fas fa-network-wired"></i> Create Interface</h2>
        <form method="POST" autocomplete="off">

          <!-- Interface -->
          <label class="form-label" for="iface">Interface Name
            <small class="text-warning">Max 8 characters</small>
          </label>
          <input class="form-input" type="text" id="iface" name="iface" required
            placeholder="mywg" maxlength="8"
            oninput="if(this.value.length>8)this.value=this.value.slice(0,8);"
            value="<?php echo htmlspecialchars($iface ?? '', ENT_QUOTES); ?>">

          <!-- Private Key -->
          <label class="form-label" for="private_key">Private Key</label>
          <div class="input-wrapper">
            <input class="form-input" type="password" id="private_key" name="private_key"
              value="<?php echo WG_PRIVATE_KEY; ?>" readonly>
            <button type="button" id="togglePrivateKey" class="icon-btn">
              <i id="privateKeyIcon" class="fas fa-eye"></i>
            </button>
          </div>

          <!-- Address -->
          <label class="form-label" for="address">Address (e.g., 10.0.0.1/24)</label>
          <input class="form-input" type="text" id="address" name="address" required
            placeholder="10.0.0.1/24"
            value="<?php echo htmlspecialchars($address ?? '', ENT_QUOTES); ?>">

          <!-- Listen Port -->
          <label class="form-label" for="listen_port">Listen Port (default: 51820)</label>
          <input class="form-input" type="number" id="listen_port" name="listen_port"
            placeholder="51820"
            value="<?php echo htmlspecialchars($listen_port ?? '', ENT_QUOTES); ?>">

          <!-- Submit -->
          <button class="btn-submit" type="submit" name="create_interface">
            <i class="fas fa-plus"></i> Create Interface
          </button>
        </form>
      </div>
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
</div>

    <script>
      // Close modal on background click
      document.getElementById('createModal').onclick = function(e) {
        if (e.target === this) this.style.display = 'none';
      };

      // Toggle private key visibility
      const privateKeyInput = document.getElementById('private_key');
      const toggleBtn = document.getElementById('togglePrivateKey');
      const icon = document.getElementById('privateKeyIcon');
      if (privateKeyInput && toggleBtn && icon) {
        toggleBtn.addEventListener('click', function(e) {
          e.preventDefault();
          if (privateKeyInput.type === 'password') {
            privateKeyInput.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
          } else {
            privateKeyInput.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
          }
        });
      }
    </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>