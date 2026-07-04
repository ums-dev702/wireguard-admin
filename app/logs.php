<?php 
require_once __DIR__ . '/../includes/header.php';

// Pagination parameters
$limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Filter parameters
$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$user_filter = isset($_GET['user']) ? trim($_GET['user']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$success_message = '';
$error_message = '';

try {
    // Handle delete all logs action
    if (isset($_POST['delete_all_logs']) && $_POST['confirm_delete'] === 'DELETE_ALL_LOGS') {
        $user_id = $currentUser['id'] ?? null;
        
        try {
            // Get count before deletion for logging
            $count_result = $db->select("SELECT COUNT(*) as total FROM audit_log");
            $logs_count = $count_result[0]['total'];
            
            // Delete all logs
            $db->query("DELETE FROM audit_log");
            
            // Log this action (ironic, but important for security)
            $auth->logActivity(
                $user_id,
                'DELETE_ALL_LOGS',
                "Deleted all audit logs ({$logs_count} entries)",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            );
            
            $success_message = "Successfully deleted {$logs_count} audit log entries.";
            
            // Reset pagination after deletion
            $page = 1;
            $offset = 0;
            
        } catch (Exception $e) {
            $error_message = "Failed to delete audit logs: " . $e->getMessage();
        }
    }

    // Build query with filters
    $where_conditions = [];
    $params = [];

    if ($action_filter) {
        $where_conditions[] = "al.action LIKE ?";
        $params[] = "%{$action_filter}%";
    }

    if ($user_filter) {
        $where_conditions[] = "u.username LIKE ?";
        $params[] = "%{$user_filter}%";
    }

    if ($date_from) {
        $where_conditions[] = "al.created_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }

    if ($date_to) {
        $where_conditions[] = "al.created_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }

    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM audit_log al LEFT JOIN users u ON al.user_id = u.id {$where_clause}";
    $count_result = $db->select($count_query, $params);
    $total_logs = $count_result[0]['total'];
    $total_pages = ceil($total_logs / $limit);

    // Get logs with filters and pagination
    $query = "SELECT al.*, u.username 
              FROM audit_log al 
              LEFT JOIN users u ON al.user_id = u.id 
              {$where_clause}
              ORDER BY al.created_at DESC 
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $logs = $db->select($query, $params);

    // Get unique actions for filter dropdown
    $actions_query = "SELECT DISTINCT action FROM audit_log ORDER BY action";
    $actions_result = $db->select($actions_query);
    $available_actions = array_column($actions_result, 'action');

} catch (Exception $e) {
    $error_message = "Error loading audit logs: " . $e->getMessage();
    $logs = [];
    $total_logs = 0;
    $total_pages = 0;
    $available_actions = [];
}
?>

<style>
    .portal-hero {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        border: 1px solid rgba(56, 189, 248, 0.18);
        background:
            radial-gradient(circle at 14% 20%, rgba(56, 189, 248, 0.2), transparent 32%),
            radial-gradient(circle at 86% 18%, rgba(16, 185, 129, 0.16), transparent 30%),
            linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(2, 6, 23, 0.76));
        box-shadow: 0 26px 90px rgba(0, 0, 0, 0.3);
    }

    .portal-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(rgba(56, 189, 248, 0.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(56, 189, 248, 0.045) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: linear-gradient(90deg, black, transparent);
        pointer-events: none;
    }

    .portal-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border-radius: 16px;
        padding: 0.75rem 1rem;
        font-weight: 800;
    }

    .portal-button-primary {
        color: #fff;
        background: linear-gradient(135deg, #38bdf8, #2563eb);
        box-shadow: 0 14px 36px rgba(56, 189, 248, 0.18);
    }

    .portal-button-danger {
        color: #fff;
        background: linear-gradient(135deg, #ef4444, #b91c1c);
        box-shadow: 0 14px 36px rgba(239, 68, 68, 0.18);
    }

    .portal-shell .glass-card {
        border-radius: 24px;
    }

    .portal-shell thead {
        background: rgba(2, 6, 23, 0.82) !important;
    }

    .portal-shell tbody tr:hover {
        background: rgba(56, 189, 248, 0.055) !important;
    }

    .audit-stat {
        border-radius: 24px;
        border: 1px solid rgba(148, 163, 184, 0.16);
        background: rgba(15, 23, 42, 0.72);
        backdrop-filter: blur(18px);
    }
</style>

<!-- Logs Content -->
<div class="p-4 lg:p-6 portal-shell">
    <section class="portal-hero p-5 lg:p-7 mb-6">
        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full border border-blue-400 border-opacity-20 bg-blue-500 bg-opacity-10 text-blue-300 text-sm font-bold mb-4">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse mr-2"></span>
                    Security Activity
                </span>
                <h1 class="text-3xl lg:text-5xl font-black text-white leading-tight">Audit Log Portal</h1>
                <p class="text-gray-300 text-base lg:text-lg mt-4 max-w-3xl">
                    Search VPN admin activity, review access events, and monitor system changes.
                </p>
                <div class="flex flex-wrap gap-3 mt-6">
                    <span class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 px-4 py-3 text-sm text-gray-300">
                        Total logs: <strong class="text-blue-300"><?= number_format($total_logs) ?></strong>
                    </span>
                    <span class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 px-4 py-3 text-sm text-gray-300">
                        Action types: <strong class="text-green-300"><?= count($available_actions) ?></strong>
                    </span>
                    <span class="rounded-2xl bg-white bg-opacity-5 border border-white border-opacity-10 px-4 py-3 text-sm text-gray-300">
                        Page: <strong class="text-white"><?= $page ?><?= $total_pages ? ' / ' . $total_pages : '' ?></strong>
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row xl:flex-col gap-3 xl:min-w-56">
                <button onclick="exportLogs()" class="portal-button portal-button-primary">
                    <i class="fas fa-download"></i>Export Logs
                </button>
                <button onclick="showDeleteAllModal()" class="portal-button portal-button-danger">
                    <i class="fas fa-trash-alt"></i>Delete All
                </button>
            </div>
        </div>
    </section>

    <?php if (isset($success_message) && $success_message): ?>
    <!-- Success Message -->
    <div class="glass-card p-4 mb-6 border-l-4 border-green-500">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-400 mr-3"></i>
            <span class="text-green-400"><?= htmlspecialchars($success_message) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <!-- Error Message -->
    <div class="glass-card p-4 mb-6 border-l-4 border-red-500">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-red-400 mr-3"></i>
            <span class="text-red-400"><?= htmlspecialchars($error_message) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="glass-card p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 bg-blue-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-list text-blue-400"></i>
                </div>
                <span class="text-2xl font-bold text-white"><?= number_format($total_logs) ?></span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mt-2">Total Logs</h3>
        </div>

        <div class="glass-card p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 bg-green-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-day text-green-400"></i>
                </div>
                <span class="text-2xl font-bold text-white">
                    <?php
                    $today_logs = array_filter($logs, function($log) {
                        return date('Y-m-d', strtotime($log['created_at'])) === date('Y-m-d');
                    });
                    echo count($today_logs);
                    ?>
                </span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mt-2">Today</h3>
        </div>

        <div class="glass-card p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 bg-yellow-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-yellow-400"></i>
                </div>
                <span class="text-2xl font-bold text-white">
                    <?php
                    $unique_users = array_unique(array_column($logs, 'username'));
                    echo count(array_filter($unique_users));
                    ?>
                </span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mt-2">Active Users</h3>
        </div>

        <div class="glass-card p-4">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 bg-purple-500 bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-cogs text-purple-400"></i>
                </div>
                <span class="text-2xl font-bold text-white"><?= count($available_actions) ?></span>
            </div>
            <h3 class="text-sm font-medium text-gray-400 mt-2">Action Types</h3>
        </div>
    </div>

    <!-- Filters -->
    <div class="glass-card p-4 lg:p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">Filters</h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <!-- Action Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Action</label>
                <select name="action" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
                    <option value="">All Actions</option>
                    <?php foreach ($available_actions as $action): ?>
                    <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter === $action ? 'selected' : '' ?>>
                        <?= htmlspecialchars($action) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- User Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">User</label>
                <input type="text" name="user" value="<?= htmlspecialchars($user_filter) ?>" 
                       placeholder="Username" 
                       class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Date From -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                       class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Date To -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                       class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Limit -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Per Page</label>
                <select name="limit" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
                    <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="flex gap-2 items-end">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
                <a href="logs" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="glass-card overflow-hidden">
        <div class="px-4 lg:px-6 py-4 border-b border-gray-600">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h2 class="text-lg font-semibold text-white">
                    Audit Logs 
                    <span class="text-sm font-normal text-gray-400">
                        (<?= number_format(($page - 1) * $limit + 1) ?>-<?= min($page * $limit, $total_logs) ?> of <?= number_format($total_logs) ?>)
                    </span>
                </h2>
                
                <?php if ($total_pages > 1): ?>
                <!-- Pagination -->
                <nav class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=1<?= http_build_query(array_filter(['action' => $action_filter, 'user' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'limit' => $limit]), '', '&') ?>" 
                       class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm">First</a>
                    <a href="?page=<?= $page - 1 ?><?= http_build_query(array_filter(['action' => $action_filter, 'user' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'limit' => $limit]), '', '&') ?>" 
                       class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm">Previous</a>
                    <?php endif; ?>
                    
                    <span class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                        <?= $page ?> of <?= $total_pages ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= http_build_query(array_filter(['action' => $action_filter, 'user' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'limit' => $limit]), '', '&') ?>" 
                       class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm">Next</a>
                    <a href="?page=<?= $total_pages ?><?= http_build_query(array_filter(['action' => $action_filter, 'user' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'limit' => $limit]), '', '&') ?>" 
                       class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm">Last</a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Time</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Action</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Description</th>
                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-600">
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="px-4 lg:px-6 py-8 text-center text-gray-400">
                            <i class="fas fa-search text-4xl mb-3 block"></i>
                            No audit logs found matching the criteria.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr class="hover:bg-gray-800/50 transition-colors">
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <div><?= date('M j, Y', strtotime($log['created_at'])) ?></div>
                            <div class="text-xs text-gray-500"><?= date('g:i:s A', strtotime($log['created_at'])) ?></div>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm">
                            <?php if ($log['username']): ?>
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-500 bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-blue-400 text-xs"></i>
                                </div>
                                <span class="text-white font-medium"><?= htmlspecialchars($log['username']) ?></span>
                            </div>
                            <?php else: ?>
                            <span class="text-gray-400 italic">System</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                <?php 
                                $action_lower = strtolower($log['action']);
                                if (strpos($action_lower, 'login') !== false || strpos($action_lower, 'auth') !== false) {
                                    echo 'bg-green-500 bg-opacity-10 text-green-400';
                                } elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'remove') !== false) {
                                    echo 'bg-red-500 bg-opacity-10 text-red-400';
                                } elseif (strpos($action_lower, 'create') !== false || strpos($action_lower, 'add') !== false) {
                                    echo 'bg-blue-500 bg-opacity-10 text-blue-400';
                                } elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false) {
                                    echo 'bg-yellow-500 bg-opacity-10 text-yellow-400';
                                } else {
                                    echo 'bg-gray-500 bg-opacity-10 text-gray-400';
                                }
                                ?>">
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td class="px-4 lg:px-6 py-4 text-sm text-gray-300">
                            <div class="max-w-xs truncate" title="<?= htmlspecialchars($log['description'] ?? '') ?>">
                                <?= htmlspecialchars($log['description'] ?? 'No description') ?>
                            </div>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                            <div class="flex items-center">
                                <i class="fas fa-globe-americas text-xs mr-2"></i>
                                <?= htmlspecialchars($log['ip_address'] ?? 'unknown') ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <!-- Bottom Pagination -->
        <div class="px-4 lg:px-6 py-4 border-t border-gray-600">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-400">
                    Showing <?= number_format(($page - 1) * $limit + 1) ?>-<?= min($page * $limit, $total_logs) ?> of <?= number_format($total_logs) ?> results
                </div>
                <nav class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=1<?= http_build_query(array_filter(['action' => $action_filter, 'user' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'limit' => $limit]), '', '&') ?>" 
                       class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm">First</a>
                    <a href="?page=<?= $page - 1 ?><?= http_build_query(array_filter(['action' => $action_filter, 'user' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'limit' => $limit]), '', '&') ?>" 
                       class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm">Previous</a>
                    <?php endif; ?>
                    
                    <span class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                        <?= $page ?> of <?= $total_pages ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= http_build_query(array_filter(['action' => $action_filter, 'user' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'limit' => $limit]), '', '&') ?>" 
                       class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm">Next</a>
                    <a href="?page=<?= $total_pages ?><?= http_build_query(array_filter(['action' => $action_filter, 'user' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to, 'limit' => $limit]), '', '&') ?>" 
                       class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm">Last</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete All Logs Confirmation Modal -->
<div id="deleteAllModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="glass-card p-6 max-w-md w-full mx-4">
        <div class="flex items-center mb-4">
            <div class="w-12 h-12 bg-red-500 bg-opacity-10 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-exclamation-triangle text-red-400 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-white">Delete All Audit Logs</h3>
                <p class="text-sm text-gray-400">This action cannot be undone</p>
            </div>
        </div>

        <div class="mb-6">
            <p class="text-gray-300 mb-4">
                Are you sure you want to delete <strong class="text-white"><?= number_format($total_logs) ?></strong> audit log entries? 
                This will permanently remove all security and activity logs from the system.
            </p>
            <div class="bg-yellow-900 bg-opacity-20 border border-yellow-500 rounded-lg p-3 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-warning text-yellow-400 mr-2"></i>
                    <span class="text-yellow-300 text-sm font-medium">Warning: This action is irreversible</span>
                </div>
            </div>
            <p class="text-sm text-gray-400 mb-4">
                To confirm this action, please type <code class="bg-gray-800 px-2 py-1 rounded text-red-400">DELETE_ALL_LOGS</code> in the field below:
            </p>
            
            <form method="POST" id="deleteAllForm">
                <input type="hidden" name="delete_all_logs" value="1">
                <input type="text" name="confirm_delete" id="confirmDeleteInput" 
                       class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-red-500" 
                       placeholder="Type DELETE_ALL_LOGS to confirm"
                       autocomplete="off">
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="hideDeleteAllModal()" 
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="confirmDeleteBtn" disabled
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-400 disabled:cursor-not-allowed text-white rounded-lg transition-colors">
                        <i class="fas fa-trash-alt mr-2"></i>Delete All Logs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.remove('hidden');
    document.getElementById('confirmDeleteInput').focus();
}

function hideDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.add('hidden');
    document.getElementById('deleteAllForm').reset();
    document.getElementById('confirmDeleteBtn').disabled = true;
}

function exportLogs() {
    // Create export URL with current filters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.open('export-logs.php?' + params.toString(), '_blank');
}

// Enable/disable delete button based on confirmation input
document.getElementById('confirmDeleteInput').addEventListener('input', function(e) {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.disabled = e.target.value !== 'DELETE_ALL_LOGS';
});

// Handle form submission with additional confirmation
document.getElementById('deleteAllForm').addEventListener('submit', function(e) {
    const input = document.getElementById('confirmDeleteInput').value;
    if (input !== 'DELETE_ALL_LOGS') {
        e.preventDefault();
        alert('Please type "DELETE_ALL_LOGS" to confirm this action.');
        return;
    }
    
    if (!confirm('Are you absolutely sure you want to delete ALL audit logs? This cannot be undone.')) {
        e.preventDefault();
        return;
    }
});

// Close modal when clicking outside
document.getElementById('deleteAllModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideDeleteAllModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideDeleteAllModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>