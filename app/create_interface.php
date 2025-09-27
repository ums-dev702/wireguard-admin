<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

$private_key = defined('WG_PRIVATE_KEY') ? WG_PRIVATE_KEY : '';
$rand = rand(2, 254);
$address = "10.0.0.$rand/24";
$listen_port = find_free_port();
if (!$listen_port) $listen_port = 51820;

?>
<style>
  :root {
    --primary: #10b981;
    --primary-dark: #059669;
    --danger: #dc2626;
    --dark-bg: #0f172a;
    --card-bg: rgba(255, 255, 255, 0.05);
    --text-light: #f8fafc;
    --text-muted: #94a3b8;
    --border-radius: 0.75rem;
    --transition: all 0.3s ease;
  }

  .gradient-bg {
    background: linear-gradient(135deg, #000000 0%, #1a365d 100%);
  }

  .glass-effect {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--border-radius);
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

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
  }

  .page-title {
    color: var(--text-light);
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
  }

  .btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border: none;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  }

  .btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-light);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--border-radius);
    padding: 0.5rem 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
  }

  .btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
  }

  .btn-danger {
    background: var(--danger);
    color: white;
    border: none;
    border-radius: var(--border-radius);
    padding: 0.5rem 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
  }

  .btn-danger:hover {
    background: #b91c1c;
  }

  .form-group {
    margin-bottom: 1.5rem;
  }

  .form-label {
    display: block;
    color: var(--text-light);
    font-weight: 500;
    margin-bottom: 0.5rem;
  }

  .form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius);
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-light);
    transition: var(--transition);
  }

  .form-input:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 255, 255, 0.15);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
  }

  .input-wrapper {
    position: relative;
  }

  .icon-btn {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 0.25rem;
    transition: var(--transition);
  }

  .icon-btn:hover {
    color: var(--text-light);
    background: rgba(255, 255, 255, 0.1);
  }

  .alert {
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1.5rem;
    font-weight: 500;
  }

  .alert-success {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border-left: 4px solid #10b981;
  }

  .alert-error {
    background: rgba(220, 38, 38, 0.15);
    color: #dc2626;
    border-left: 4px solid #dc2626;
  }

  .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }

  .modal-content {
    width: 100%;
    max-width: 500px;
    padding: 2rem;
    position: relative;
    animation: modalFadeIn 0.3s ease-out;
  }

  @keyframes modalFadeIn {
    from {
      opacity: 0;
      transform: scale(0.9);
    }
    to {
      opacity: 1;
      transform: scale(1);
    }
  }

  .close-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 1.5rem;
    cursor: pointer;
    transition: var(--transition);
  }

  .close-btn:hover {
    color: var(--text-light);
  }

  .modal-title {
    color: var(--text-light);
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .table-container {
    overflow-x: auto;
    border-radius: var(--border-radius);
    margin-top: 2rem;
  }

  .data-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: var(--border-radius);
    overflow: hidden;
  }

  .data-table th {
    background: rgba(16, 185, 129, 0.2);
    color: var(--text-light);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
  }

  .data-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: var(--text-light);
  }

  .data-table tr:last-child td {
    border-bottom: none;
  }

  .data-table tr:hover {
    background: rgba(255, 255, 255, 0.03);
  }

  .action-buttons {
    display: flex;
    gap: 0.5rem;
  }

  .empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
  }

  .empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
  }

  .text-warning {
    color: #f59e0b;
  }

  .text-muted {
    color: var(--text-muted);
    font-size: 0.875rem;
  }

  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
    }

    .page-title {
      font-size: 1.5rem;
    }

    .modal-content {
      padding: 1.5rem;
    }

    .data-table {
      font-size: 0.875rem;
    }

    .data-table th,
    .data-table td {
      padding: 0.75rem 0.5rem;
    }

    .action-buttons {
      flex-direction: column;
    }

    .btn-primary, .btn-secondary, .btn-danger {
      width: 100%;
      justify-content: center;
    }
  }

  @media (max-width: 480px) {
    .page-title {
      font-size: 1.25rem;
    }

    .modal-content {
      padding: 1rem;
    }

    .data-table {
      font-size: 0.8rem;
    }
  }
</style>

<div class="p-4 lg:p-6">
  <div class="glass-effect p-6 lg:p-8">
    <div class="page-header">
      <h1 class="page-title"><i class="fas fa-network-wired mr-2"></i>WireGuard Interfaces</h1>
      <button onclick="document.getElementById('createModal').style.display='flex'" class="btn-primary">
        <i class="fas fa-plus"></i> New Interface
      </button>
    </div>
    <!-- Modal for create interface -->
    <div id="createModal" class="modal">
      <div class="modal-content glass-effect">
        <button class="close-btn" onclick="document.getElementById('createModal').style.display='none'">&times;</button>
        <h2 class="modal-title"><i class="fas fa-plus-circle"></i> Create Interface</h2>
        <form method="POST" autocomplete="off" action="backend/create_interface_backend.php">
          <div class="form-group">
            <label class="form-label" for="iface">
              Interface Name <span class="text-warning">(Max 8 characters)</span>
            </label>
            <input class="form-input" type="text" id="iface" name="iface" required
              placeholder="e.g., wg0" maxlength="8"
              oninput="if(this.value.length>8)this.value=this.value.slice(0,8);"
              value="<?php echo htmlspecialchars($iface ?? '', ENT_QUOTES); ?>">
          </div>

          <div class="form-group">
            <label class="form-label" for="private_key">Private Key</label>
            <div class="input-wrapper">
              <input class="form-input" type="password" id="private_key" name="private_key"
                value="<?php echo htmlspecialchars($private_key ?? '', ENT_QUOTES); ?>" readonly>
              <button type="button" id="togglePrivateKey" class="icon-btn">
                <i id="privateKeyIcon" class="fas fa-eye"></i>
              </button>
            </div>
            <p class="text-muted">Pre-configured private key from your environment</p>
          </div>

          <div class="form-group">
            <label class="form-label" for="address">Address</label>
            <div style="display: flex; gap: 0.5rem;">
              <input class="form-input" type="text" id="address" name="address" required
                placeholder="e.g., 10.0.0.1/24"
                value="<?php echo htmlspecialchars($address ?? '', ENT_QUOTES); ?>" style="flex: 1;">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="listen_port">Listen Port</label>
            <div style="display: flex; gap: 0.5rem;">
              <input class="form-input" type="number" id="listen_port" name="listen_port"
                placeholder="51820"
                value="<?php echo htmlspecialchars($listen_port ?? '', ENT_QUOTES); ?>" style="flex: 1;">
            </div>
          </div>

          <div class="form-group" style="margin-top: 2rem;">
            <button class="btn-primary" type="submit" name="create_interface" style="width: 100%;">
              <i class="fas fa-plus"></i> Create Interface
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Table of interfaces -->
    <div class="table-container">
      <h2 style="color: var(--text-light); font-size: 1.25rem; margin-bottom: 1rem;">All Interfaces</h2>
      
      <?php
      try {
        ensure_interfaces_table();
        $db = get_db();
        $rows = $db->query('SELECT * FROM interfaces ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Address</th>
                <th>Port</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td>#<?= htmlspecialchars($row['id']) ?></td>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td><?= htmlspecialchars($row['address']) ?></td>
                  <td><?= htmlspecialchars($row['port']) ?></td>
                  <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                  <td>
                    <div class="action-buttons">
                      <button class="btn-secondary" onclick="alert('Edit functionality coming soon')">
                        <i class="fas fa-edit"></i> Edit
                      </button>
                      <form method="POST" onsubmit="return confirm('Are you sure you want to delete this interface?');" style="display: inline;">
                        <input type="hidden" name="delete_id" value="<?= htmlspecialchars($row['id']) ?>">
                        <button class="btn-danger" type="submit">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state glass-effect">
            <i class="fas fa-network-wired"></i>
            <h3 style="color: var(--text-light); margin-bottom: 0.5rem;">No Interfaces Found</h3>
            <p class="text-muted">Get started by creating your first WireGuard interface.</p>
            <button onclick="document.getElementById('createModal').style.display='flex'" class="btn-primary" style="margin-top: 1rem;">
              <i class="fas fa-plus"></i> Create Interface
            </button>
          </div>
        <?php endif;
      } catch (Exception $e) {
        echo '<div class="alert alert-error">Error loading interfaces: ' . htmlspecialchars($e->getMessage()) . '</div>';
      }
      ?>
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

  // Close modal with Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      document.getElementById('createModal').style.display = 'none';
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>