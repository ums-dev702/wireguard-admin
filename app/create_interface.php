<?php
require_once __DIR__ . '/../includes/header.php';

function ci_escape($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ci_next_interface_name(array $rows): string
{
  $used = [];
  foreach ($rows as $row) {
    $used[] = (string) ($row['name'] ?? '');
  }

  for ($i = 1; $i <= 99; $i++) {
    $name = 'vpn' . $i;
    if (!in_array($name, $used, true)) {
      return $name;
    }
  }

  return 'vpn' . random_int(100, 999);
}

function ci_fast_subnet(): string
{
  return '10.' . random_int(10, 250) . '.' . random_int(1, 250) . '.1/24';
}

function ci_fast_port(): int
{
  return random_int(20000, 60000);
}

$private_key = defined('WG_PRIVATE_KEY') ? WG_PRIVATE_KEY : '';
$rows = [];
$loadError = null;

try {
  ensure_interfaces_table();
  $db = get_db();
  $rows = $db->query('SELECT * FROM interfaces ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $loadError = $e->getMessage();
}

$hasInterfaces = count($rows) > 0;
$iface = ci_next_interface_name($rows);
$address = ci_fast_subnet();
$listen_port = ci_fast_port();
$activeCount = count(array_filter($rows, fn($row) => ($row['status'] ?? '') === 'active'));
?>

<style>
  .interface-hero {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    border: 1px solid rgba(16, 185, 129, 0.18);
    background:
      radial-gradient(circle at 14% 16%, rgba(20, 241, 164, 0.22), transparent 32%),
      radial-gradient(circle at 82% 18%, rgba(56, 189, 248, 0.16), transparent 30%),
      linear-gradient(135deg, rgba(15, 23, 42, 0.88), rgba(2, 6, 23, 0.76));
    box-shadow: 0 26px 90px rgba(0, 0, 0, 0.28);
  }

  .interface-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(rgba(16, 185, 129, 0.045) 1px, transparent 1px),
      linear-gradient(90deg, rgba(16, 185, 129, 0.045) 1px, transparent 1px);
    background-size: 34px 34px;
    mask-image: linear-gradient(90deg, black, transparent);
    pointer-events: none;
  }

  .quick-form-card,
  .interface-table-card {
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 26px;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(18px);
    box-shadow: 0 20px 75px rgba(0, 0, 0, 0.22);
  }

  .field-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.5rem;
    color: #e2e8f0;
    font-weight: 750;
  }

  .field-hint {
    color: #94a3b8;
    font-size: 0.78rem;
    font-weight: 500;
  }

  .quick-input {
    width: 100%;
    padding: 0.95rem 1rem;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    background: rgba(2, 6, 23, 0.58);
    color: #fff;
  }

  .quick-input[readonly] {
    opacity: 0.85;
  }

  .quick-input:focus {
    outline: none;
    border-color: rgba(16, 185, 129, 0.85);
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
  }

  .quick-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    border-radius: 16px;
    font-weight: 800;
    padding: 0.85rem 1.1rem;
  }

  .secondary-button {
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(255, 255, 255, 0.06);
    color: #e2e8f0;
  }

  .secondary-button:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(16, 185, 129, 0.28);
  }

  .danger-button {
    border-radius: 14px;
    background: rgba(239, 68, 68, 0.14);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.22);
    padding: 0.65rem 0.85rem;
    font-weight: 750;
  }

  .danger-button:hover {
    background: rgba(239, 68, 68, 0.22);
  }

  .interface-row {
    border-bottom: 1px solid rgba(148, 163, 184, 0.1);
  }

  .interface-row:last-child {
    border-bottom: 0;
  }

  .interface-row:hover {
    background: rgba(16, 185, 129, 0.055);
  }

  .status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.35rem 0.7rem;
    font-size: 0.78rem;
    font-weight: 800;
  }

  .port-validation {
    display: none;
    margin-top: 0.65rem;
    padding: 0.75rem;
    border-radius: 14px;
    font-size: 0.88rem;
    font-weight: 650;
  }

  .port-validation.success {
    display: block;
    color: #86efac;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
  }

  .port-validation.error {
    display: block;
    color: #fca5a5;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
  }

  .port-validation.checking {
    display: block;
    color: #cbd5e1;
    background: rgba(148, 163, 184, 0.1);
    border: 1px solid rgba(148, 163, 184, 0.16);
  }

  .empty-fast-card {
    border: 1px dashed rgba(16, 185, 129, 0.32);
    border-radius: 22px;
    background: rgba(16, 185, 129, 0.06);
  }

  .modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(2, 6, 23, 0.78);
    backdrop-filter: blur(10px);
  }

  .modal-panel {
    width: min(540px, 100%);
    border-radius: 24px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(15, 23, 42, 0.96);
    box-shadow: 0 30px 100px rgba(0, 0, 0, 0.45);
  }
</style>

<div class="p-4 lg:p-6 space-y-6">
  <section class="interface-hero p-5 lg:p-7">
    <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
      <div>
        <span class="inline-flex items-center px-3 py-1 rounded-full border border-green-400 border-opacity-20 bg-green-500 bg-opacity-10 text-green-300 text-sm font-bold mb-4">
          <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse mr-2"></span>
          Fast Interface Setup
        </span>
        <h2 class="text-3xl lg:text-5xl font-black text-white leading-tight">Create WireGuard interfaces faster.</h2>
        <p class="text-gray-300 text-base lg:text-lg mt-4 max-w-3xl">
          Use generated defaults, create the first tunnel quickly, then manage all VPN interfaces from one clean page.
        </p>
      </div>

      <div class="grid grid-cols-3 gap-3 min-w-full sm:min-w-0 sm:w-auto">
        <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
          <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">Total</p>
          <p class="text-2xl font-black text-white mt-1"><?= count($rows) ?></p>
        </div>
        <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
          <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">Active</p>
          <p class="text-2xl font-black text-green-300 mt-1"><?= $activeCount ?></p>
        </div>
        <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
          <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">Next</p>
          <p class="text-xl font-black text-cyan-300 mt-2">wg_<?= ci_escape($iface) ?></p>
        </div>
      </div>
    </div>
  </section>

  <?php if ($loadError): ?>
    <div class="rounded-2xl bg-red-500 bg-opacity-10 border border-red-400 border-opacity-20 text-red-100 p-4">
      <i class="fas fa-exclamation-triangle mr-2"></i>
      Error loading interfaces: <?= ci_escape($loadError) ?>
    </div>
  <?php endif; ?>

  <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    <div class="xl:col-span-1 quick-form-card p-5 lg:p-6">
      <div class="flex items-start justify-between mb-6">
        <div>
          <p class="text-sm uppercase tracking-widest text-green-300 font-bold">Quick Create</p>
          <h2 class="text-2xl font-black text-white mt-1"><?= $hasInterfaces ? 'New Interface' : 'Create First Interface' ?></h2>
          <p class="text-gray-400 mt-2">Open a focused setup modal with generated interface defaults.</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-green-500 bg-opacity-10 flex items-center justify-center">
          <i class="fas fa-bolt text-green-300"></i>
        </div>
      </div>

      <?php if (!$private_key): ?>
        <div class="mb-5 rounded-2xl bg-yellow-500 bg-opacity-10 border border-yellow-400 border-opacity-20 text-yellow-100 p-4">
          <i class="fas fa-key mr-2"></i>
          `WG_PRIVATE_KEY` is empty in config. Add it before creating an interface.
        </div>
      <?php endif; ?>

      <div class="rounded-3xl bg-white bg-opacity-5 border border-white border-opacity-10 p-5 mb-5">
        <div class="flex items-center justify-between mb-4">
          <span class="text-sm text-gray-400 font-bold">Next interface</span>
          <span class="status-chip bg-green-500 bg-opacity-10 text-green-300">
            <span class="w-2 h-2 rounded-full bg-green-400"></span>
            Ready
          </span>
        </div>
        <p class="text-3xl font-black text-white">wg_<?= ci_escape($iface) ?></p>
        <p class="text-sm text-gray-400 mt-2">Subnet and port will be generated when the modal opens.</p>
      </div>

      <button class="quick-button w-full text-white" type="button" onclick="openCreateModal(true)" <?= !$private_key ? 'disabled' : '' ?>>
        <i class="fas fa-plus-circle"></i>
        <?= $hasInterfaces ? 'Open New Interface Form' : 'Create First Interface' ?>
      </button>

      <div class="grid grid-cols-2 gap-3 mt-4">
        <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
          <p class="text-xs text-gray-500 uppercase tracking-widest font-bold">Generated IP</p>
          <p class="text-sm font-mono text-cyan-300 mt-2"><?= ci_escape($address) ?></p>
        </div>
        <div class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 p-4">
          <p class="text-xs text-gray-500 uppercase tracking-widest font-bold">Generated Port</p>
          <p class="text-sm font-mono text-green-300 mt-2"><?= ci_escape($listen_port) ?></p>
        </div>
      </div>
    </div>

    <div class="xl:col-span-2 interface-table-card p-5 lg:p-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
        <div>
          <p class="text-sm uppercase tracking-widest text-cyan-300 font-bold">Interface Manager</p>
          <h2 class="text-2xl font-black text-white mt-1">All WireGuard Interfaces</h2>
        </div>
        <button class="quick-button secondary-button" onclick="openCreateModal(true)">
          <i class="fas fa-plus"></i> New Interface
        </button>
      </div>

      <?php if (!$hasInterfaces): ?>
        <div class="empty-fast-card p-8 text-center">
          <div class="w-16 h-16 mx-auto rounded-3xl bg-green-500 bg-opacity-10 flex items-center justify-center mb-4">
            <i class="fas fa-network-wired text-2xl text-green-300"></i>
          </div>
          <h3 class="text-2xl font-black text-white mb-2">No interfaces yet</h3>
          <p class="text-gray-400 max-w-xl mx-auto">
            WireGuard is not ready until at least one interface exists. The quick form is already filled in, so you can create one immediately.
          </p>
          <button class="quick-button text-white mt-5" onclick="openCreateModal(true)">
            <i class="fas fa-plus-circle"></i> Open Create Form
          </button>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-xs uppercase tracking-widest text-gray-500 border-b border-gray-700 border-opacity-60">
                <th class="py-3 px-3">Interface</th>
                <th class="py-3 px-3">Address</th>
                <th class="py-3 px-3">Port</th>
                <th class="py-3 px-3">Status</th>
                <th class="py-3 px-3">Created</th>
                <th class="py-3 px-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr class="interface-row">
                  <td class="py-4 px-3">
                    <a href="wg_status?interface=wg_<?= urlencode($row['name']) ?>" class="inline-flex items-center text-white font-bold hover:text-green-300">
                      <span class="w-10 h-10 rounded-2xl bg-green-500 bg-opacity-10 flex items-center justify-center mr-3">
                        <i class="fas fa-network-wired text-green-300"></i>
                      </span>
                      <span>
                        wg_<?= ci_escape($row['name']) ?>
                        <span class="block text-xs text-gray-500 font-mono"><?= ci_escape($row['iface_id']) ?></span>
                      </span>
                    </a>
                  </td>
                  <td class="py-4 px-3 font-mono text-gray-300"><?= ci_escape($row['address']) ?></td>
                  <td class="py-4 px-3 font-mono text-gray-300"><?= ci_escape($row['port']) ?></td>
                  <td class="py-4 px-3">
                    <?php $active = ($row['status'] ?? '') === 'active'; ?>
                    <span class="status-chip <?= $active ? 'bg-green-500 bg-opacity-10 text-green-300' : 'bg-red-500 bg-opacity-10 text-red-300' ?>">
                      <span class="w-2 h-2 rounded-full <?= $active ? 'bg-green-400' : 'bg-red-400' ?>"></span>
                      <?= ci_escape($row['status']) ?>
                    </span>
                  </td>
                  <td class="py-4 px-3 text-gray-400"><?= !empty($row['created_at']) ? date('M j, Y', strtotime($row['created_at'])) : 'N/A' ?></td>
                  <td class="py-4 px-3">
                    <div class="flex justify-end gap-2">
                      <button class="quick-button secondary-button py-2 px-3"
                        onclick="openEditModal('<?= ci_escape($row['iface_id']) ?>', '<?= ci_escape($row['name']) ?>', '<?= ci_escape($row['address']) ?>', '<?= ci_escape($row['port']) ?>')">
                        <i class="fas fa-edit"></i>
                      </button>
                      <form method="POST" action="app/backend/create_interface_backend.php" onsubmit="return confirm('Delete wg_<?= ci_escape($row['name']) ?>?');">
                        <input type="hidden" name="delete_id" value="<?= ci_escape($row['id']) ?>">
                        <button class="danger-button" type="submit">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<div id="createModal" class="modal">
  <div class="modal-panel p-5 lg:p-6">
    <div class="flex items-start justify-between mb-5">
      <div>
        <p class="text-sm uppercase tracking-widest text-green-300 font-bold">Quick Create</p>
        <h2 class="text-2xl font-black text-white mt-1"><?= $hasInterfaces ? 'New Interface' : 'Create First Interface' ?></h2>
        <p class="text-gray-400 mt-2">Generated defaults are ready. Change anything before creating.</p>
      </div>
      <button class="text-gray-400 hover:text-white text-2xl" onclick="closeCreateModal()">&times;</button>
    </div>

    <?php if (!$private_key): ?>
      <div class="mb-5 rounded-2xl bg-yellow-500 bg-opacity-10 border border-yellow-400 border-opacity-20 text-yellow-100 p-4">
        <i class="fas fa-key mr-2"></i>
        `WG_PRIVATE_KEY` is empty in config. Add it before creating an interface.
      </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" action="app/backend/create_interface_backend.php" class="space-y-5" id="createInterfaceForm">
      <input type="hidden" name="action" value="create_interface">

      <div>
        <label class="field-label" for="iface">
          Interface Suffix
          <span class="field-hint">Creates wg_&lt;name&gt;</span>
        </label>
        <div class="flex gap-2">
          <input class="quick-input" type="text" id="iface" name="iface" required maxlength="8"
            placeholder="vpn1" value="<?= ci_escape($iface) ?>" style="flex: 1;">
          <button type="button" class="quick-button secondary-button" onclick="generateFastDefaults(true)">
            <i class="fas fa-wand-magic-sparkles"></i>
          </button>
        </div>
      </div>

      <div>
        <label class="field-label" for="address">
          Tunnel Address
          <span class="field-hint">Private /24 subnet</span>
        </label>
        <input class="quick-input" type="text" id="address" name="address" required
          placeholder="10.20.1.1/24" value="<?= ci_escape($address) ?>">
      </div>

      <div>
        <label class="field-label" for="listen_port">
          Listen Port
          <span class="field-hint">UDP 20000-60000</span>
        </label>
        <div class="flex gap-2">
          <input class="quick-input" type="number" id="listen_port" name="listen_port" min="1" max="65535"
            value="<?= ci_escape($listen_port) ?>" style="flex: 1;" onblur="validatePort(this.value)">
          <button type="button" id="checkPortBtn" class="quick-button secondary-button" onclick="checkPortAvailability()">
            <i class="fas fa-search"></i>
          </button>
        </div>
        <div id="portValidationMessage" class="port-validation"></div>
      </div>

      <div>
        <label class="field-label" for="private_key">
          Private Key
          <span class="field-hint">From config</span>
        </label>
        <div class="flex gap-2">
          <input class="quick-input" type="password" id="private_key" name="private_key"
            value="<?= ci_escape($private_key) ?>" readonly style="flex: 1;">
          <button type="button" id="togglePrivateKey" class="quick-button secondary-button">
            <i id="privateKeyIcon" class="fas fa-eye"></i>
          </button>
        </div>
      </div>

      <button class="quick-button w-full text-white" type="submit" name="create_interface" <?= !$private_key ? 'disabled' : '' ?>>
        <i class="fas fa-plus-circle"></i>
        <?= $hasInterfaces ? 'Create Interface Fast' : 'Create First Interface Fast' ?>
      </button>
    </form>
  </div>
</div>

<div id="editModal" class="modal">
  <div class="modal-panel p-5 lg:p-6">
    <div class="flex items-start justify-between mb-5">
      <div>
        <p class="text-sm uppercase tracking-widest text-green-300 font-bold">Edit Interface</p>
        <h2 class="text-2xl font-black text-white mt-1">Update Tunnel Settings</h2>
      </div>
      <button class="text-gray-400 hover:text-white text-2xl" onclick="closeEditModal()">&times;</button>
    </div>

    <form method="POST" action="app/backend/create_interface_backend.php" class="space-y-5">
      <input type="hidden" name="action" value="edit_interface">
      <input type="hidden" id="edit_iface_id" name="iface_id" value="">
      <input type="hidden" id="edit_iface_name" name="iface_name" value="">

      <div>
        <label class="field-label" for="edit_interface_name">Interface</label>
        <input class="quick-input" type="text" id="edit_interface_name" readonly>
      </div>

      <div>
        <label class="field-label" for="edit_address">Address</label>
        <input class="quick-input" type="text" id="edit_address" name="address" required>
      </div>

      <div>
        <label class="field-label" for="edit_port">Listen Port</label>
        <div class="flex gap-2">
          <input class="quick-input" type="number" id="edit_port" name="port" required min="1" max="65535" style="flex: 1;" onblur="validateEditPort(this.value)">
          <button type="button" id="checkEditPortBtn" class="quick-button secondary-button" onclick="checkEditPortAvailability()">
            <i class="fas fa-search"></i>
          </button>
        </div>
        <div id="editPortValidationMessage" class="port-validation"></div>
      </div>

      <button class="quick-button w-full text-white" type="submit" name="edit_interface">
        <i class="fas fa-save"></i> Save Changes
      </button>
    </form>
  </div>
</div>

<script>
  const usedInterfaceNames = <?= json_encode(array_values(array_map(fn($row) => (string) ($row['name'] ?? ''), $rows))) ?>;

  function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  function nextInterfaceName() {
    for (let i = 1; i <= 99; i++) {
      const name = `vpn${i}`;
      if (!usedInterfaceNames.includes(name)) {
        return name;
      }
    }
    return `vpn${randomInt(100, 999)}`.slice(0, 8);
  }

  function generateFastDefaults(changeName = false) {
    if (changeName) {
      document.getElementById('iface').value = nextInterfaceName();
    }

    document.getElementById('address').value = `10.${randomInt(10, 250)}.${randomInt(1, 250)}.1/24`;
    document.getElementById('listen_port').value = randomInt(20000, 60000);

    const messageDiv = document.getElementById('portValidationMessage');
    if (messageDiv) {
      messageDiv.style.display = 'none';
      messageDiv.textContent = '';
    }
  }

  function openCreateModal(refreshDefaults = false) {
    if (refreshDefaults) {
      generateFastDefaults(true);
    }

    const modal = document.getElementById('createModal');
    modal.style.display = 'flex';

    setTimeout(() => {
      const ifaceInput = document.getElementById('iface');
      if (ifaceInput) {
        ifaceInput.focus();
        ifaceInput.select();
      }
    }, 50);
  }

  function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
  }

  function openEditModal(ifaceId, ifaceName, address, port) {
    document.getElementById('edit_iface_id').value = ifaceId;
    document.getElementById('edit_iface_name').value = ifaceName;
    document.getElementById('edit_interface_name').value = `wg_${ifaceName}`;
    document.getElementById('edit_address').value = address;
    document.getElementById('edit_port').value = port;
    document.getElementById('editModal').style.display = 'flex';
  }

  function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
  }

  document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) closeCreateModal();
  });

  document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeCreateModal();
      closeEditModal();
    }
  });

  const privateKeyInput = document.getElementById('private_key');
  const toggleBtn = document.getElementById('togglePrivateKey');
  const icon = document.getElementById('privateKeyIcon');
  if (privateKeyInput && toggleBtn && icon) {
    toggleBtn.addEventListener('click', function(e) {
      e.preventDefault();
      privateKeyInput.type = privateKeyInput.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    });
  }

  function validatePort(port) {
    if (!port || port < 1 || port > 65535) return;
    showPortMessage('portValidationMessage', 'checking', '<i class="fas fa-spinner fa-spin"></i> Checking port availability...');
    checkPortWithAjax(port, 'portValidationMessage');
  }

  function validateEditPort(port) {
    if (!port || port < 1 || port > 65535) return;
    showPortMessage('editPortValidationMessage', 'checking', '<i class="fas fa-spinner fa-spin"></i> Checking port availability...');
    checkPortWithAjax(port, 'editPortValidationMessage');
  }

  function checkPortAvailability() {
    const port = document.getElementById('listen_port').value;
    if (!port) {
      alert('Please enter a port number first');
      return;
    }
    validatePort(port);
  }

  function checkEditPortAvailability() {
    const port = document.getElementById('edit_port').value;
    if (!port) {
      alert('Please enter a port number first');
      return;
    }
    validateEditPort(port);
  }

  function showPortMessage(targetDivId, state, html) {
    const messageDiv = document.getElementById(targetDivId);
    messageDiv.className = `port-validation ${state}`;
    messageDiv.innerHTML = html;
    messageDiv.style.display = 'block';
  }

  function checkPortWithAjax(port, targetDivId) {
    fetch('app/backend/port_validator.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'port=' + encodeURIComponent(port)
    })
      .then(response => response.json())
      .then(data => {
        if (data.valid) {
          showPortMessage(targetDivId, 'success', '<i class="fas fa-check-circle"></i> ' + data.message);
        } else {
          showPortMessage(targetDivId, 'error', '<i class="fas fa-exclamation-triangle"></i> ' + data.message);
        }
      })
      .catch(() => {
        showPortMessage(targetDivId, 'error', '<i class="fas fa-exclamation-triangle"></i> Error checking port availability');
      });
  }

  document.getElementById('createInterfaceForm').addEventListener('submit', function() {
    const button = this.querySelector('button[type="submit"]');
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating interface...';
    button.disabled = true;
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
