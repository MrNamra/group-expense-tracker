<?php
/**
 * SplitWise PRO - Comprehensive Administrator Control Panel
 * 
 * Capabilities:
 * - Monitor all platform activity & metrics in real-time
 * - Dynamic Payment Apps management (Add, edit, remove, hide/unhide, custom deep links)
 * - Passwordless user impersonation ("Login As User")
 * - User management (Edit username, email, admin status, change password, delete)
 * - All platform groups & expenses management
 * - Full audit activity logging
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Handle Exit Impersonation if requested via GET
if (isset($_GET['action']) && $_GET['action'] === 'exit_impersonate') {
    if (exitImpersonation()) {
        header("Location: admin?msg=" . urlencode("Exited impersonation mode. Returned to admin account."));
        exit;
    }
}

// Require Administrator Privileges
requireAdmin();

$currentUser = getCurrentUser();
$csrfToken   = generateCSRFToken();
$validTabs = ['overview', 'apps', 'users', 'groups', 'expenses'];
$activeTab = $_GET['tab'] ?? 'overview';
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'overview';
}

$flashMsg   = $_GET['msg'] ?? null;
$flashError = $_GET['err'] ?? null;

// ── GET ACTIONS ─────────────────────────────────────────────────────────────

// 1. Passwordless User Impersonation
if (isset($_GET['action']) && $_GET['action'] === 'impersonate') {
    $targetId = (int)($_GET['user_id'] ?? 0);
    if ($targetId > 0) {
        $result = impersonateUser($targetId);
        if ($result['success']) {
            header("Location: dashboard?msg=" . urlencode("Switched session to " . $result['target_user']['username'] . " without password."));
            exit;
        } else {
            $flashError = $result['message'];
        }
    }
}

// 2. Toggle Payment App Status (Hide / Unhide)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_app') {
    $appId = (int)($_GET['id'] ?? 0);
    if ($appId > 0) {
        togglePaymentApp($appId);
        header("Location: admin?tab=apps&msg=" . urlencode("Payment app visibility updated."));
        exit;
    }
}

// 3. Delete Payment App
if (isset($_GET['action']) && $_GET['action'] === 'delete_app') {
    $appId = (int)($_GET['id'] ?? 0);
    if ($appId > 0) {
        deletePaymentApp($appId);
        header("Location: admin?tab=apps&msg=" . urlencode("Payment app removed."));
        exit;
    }
}

// 4. Reset Payment Apps to Defaults
if (isset($_GET['action']) && $_GET['action'] === 'reset_apps') {
    resetPaymentAppsToDefaults();
    header("Location: admin?tab=apps&msg=" . urlencode("Payment apps restored to system defaults."));
    exit;
}

// ── POST ACTIONS ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $flashError = "Invalid security token. Please try again.";
    } else {
        $postAction = $_POST['action'] ?? '';

        // Save / Update Payment App
        if ($postAction === 'save_app') {
            $res = savePaymentApp($_POST);
            if ($res['success']) {
                header("Location: admin?tab=apps&msg=" . urlencode($res['message']));
                exit;
            } else {
                $flashError = $res['message'];
                $activeTab = 'apps';
            }
        }

        // Edit User Details
        if ($postAction === 'edit_user') {
            $uId     = (int)($_POST['user_id'] ?? 0);
            $uName   = trim($_POST['username'] ?? '');
            $uEmail  = trim($_POST['email'] ?? '');
            $uAdmin  = isset($_POST['is_admin']) ? 1 : 0;

            $res = updateUserByAdmin($uId, $uName, $uEmail, $uAdmin);
            if ($res['success']) {
                header("Location: admin?tab=users&msg=" . urlencode($res['message']));
                exit;
            } else {
                $flashError = $res['message'];
                $activeTab = 'users';
            }
        }

        // Change User Password
        if ($postAction === 'change_password') {
            $uId     = (int)($_POST['user_id'] ?? 0);
            $newPass = $_POST['new_password'] ?? '';

            $res = changeUserPasswordByAdmin($uId, $newPass);
            if ($res['success']) {
                header("Location: admin?tab=users&msg=" . urlencode("Password for user #{$uId} has been successfully updated."));
                exit;
            } else {
                $flashError = $res['message'];
                $activeTab = 'users';
            }
        }

        // Delete User
        if ($postAction === 'delete_user') {
            $uId = (int)($_POST['user_id'] ?? 0);
            if ($uId === (int)$currentUser['id']) {
                $flashError = "You cannot delete your own active administrator account.";
                $activeTab = 'users';
            } else {
                $res = deleteUserByAdmin($uId);
                if ($res['success']) {
                    header("Location: admin?tab=users&msg=" . urlencode("User deleted successfully."));
                    exit;
                } else {
                    $flashError = $res['message'];
                    $activeTab = 'users';
                }
            }
        }

        // Delete Group
        if ($postAction === 'delete_group') {
            $gId = (int)($_POST['group_id'] ?? 0);
            if ($gId > 0) {
                deleteGroup($gId);
                header("Location: admin?tab=groups&msg=" . urlencode("Group deleted successfully."));
                exit;
            }
        }
    }
}

// ── FETCH DATA ACCORDING TO ACTIVE TAB ──────────────────────────────────────
$stats = getPlatformStats();

$paymentApps = [];
if ($activeTab === 'apps' || $activeTab === 'overview') {
    $paymentApps = getAllPaymentApps();
}

$allUsers = [];
if ($activeTab === 'users' || $activeTab === 'overview') {
    $allUsers = getAllUsersForAdmin();
}

$allGroups = [];
if ($activeTab === 'groups') {
    $allGroups = getAllGroupsForAdmin();
}

$allExpenses = [];
if ($activeTab === 'expenses' || $activeTab === 'overview') {
    $allExpenses = getAllExpensesForAdmin(100);
}

$pageTitle = "Admin Control Panel";
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-container">

    <!-- Top Header -->
    <div class="admin-header-box">
        <div>
            <div style="display:flex; align-items:center; gap:0.6rem;">
                <h1 style="font-size:1.8rem; margin:0;">🛡️ Admin Control Panel</h1>
                <span class="status-badge status-admin">PRO MASTER</span>
            </div>
            <p style="color:var(--text-muted); font-size:0.88rem; margin-top:0.35rem;">
                Full platform oversight, dynamic payment app deep links, passwordless user access & live metrics.
            </p>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="migrate" class="btn btn-secondary btn-sm" title="View DB Migrations">🛠️ Migrations</a>
            <a href="dashboard" class="btn btn-secondary btn-sm">📊 User Dashboard</a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if ($flashMsg): ?>
        <div class="card" style="background:#ecfdf5; border-color:#86efac; color:#15803d; padding:0.85rem 1.2rem; font-weight:700; margin-bottom:1.2rem; box-shadow:2px 2px 0px var(--border-ink);">
            ✅ <?= e($flashMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="card" style="background:#fef2f2; border-color:#fca5a5; color:#b91c1c; padding:0.85rem 1.2rem; font-weight:700; margin-bottom:1.2rem; box-shadow:2px 2px 0px var(--border-ink);">
            ⚠️ <?= e($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="admin-tabs-nav">
        <a href="admin?tab=overview" class="admin-tab-link <?= $activeTab === 'overview' ? 'active' : '' ?>">
            📊 Overview
        </a>
        <a href="admin?tab=apps" class="admin-tab-link <?= $activeTab === 'apps' ? 'active' : '' ?>">
            💳 Payment Apps (<?= $stats['active_apps'] ?>/<?= $stats['total_apps'] ?>)
        </a>
        <a href="admin?tab=users" class="admin-tab-link <?= $activeTab === 'users' ? 'active' : '' ?>">
            👥 Users (<?= $stats['total_users'] ?>)
        </a>
        <a href="admin?tab=groups" class="admin-tab-link <?= $activeTab === 'groups' ? 'active' : '' ?>">
            📁 Groups (<?= $stats['total_groups'] ?>)
        </a>
        <a href="admin?tab=expenses" class="admin-tab-link <?= $activeTab === 'expenses' ? 'active' : '' ?>">
            🧾 Expenses (<?= $stats['total_expenses'] ?>)
        </a>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 1: OVERVIEW                                                    -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'overview'): ?>
        
        <!-- Platform Metrics Grid -->
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">Total Users</span>
                    <div class="admin-stat-icon">👥</div>
                </div>
                <div class="admin-stat-number"><?= $stats['total_users'] ?></div>
                <span style="font-size:0.75rem; color:var(--text-dim);">Registered platform accounts</span>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">Total Groups</span>
                    <div class="admin-stat-icon">📁</div>
                </div>
                <div class="admin-stat-number"><?= $stats['total_groups'] ?></div>
                <span style="font-size:0.75rem; color:var(--text-dim);">Active expense rooms</span>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">Total Expenses</span>
                    <div class="admin-stat-icon">🧾</div>
                </div>
                <div class="admin-stat-number"><?= $stats['total_expenses'] ?></div>
                <span style="font-size:0.75rem; color:var(--text-dim);">Bills split by users</span>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">Total Volume</span>
                    <div class="admin-stat-icon">💰</div>
                </div>
                <div class="admin-stat-number" style="font-size:1.6rem; color:#10b981;">
                    <?= formatMoney($stats['total_volume'], APP_CURRENCY_SYMBOL) ?>
                </div>
                <span style="font-size:0.75rem; color:var(--text-dim);">Cumulative turnover</span>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">Settled Debts</span>
                    <div class="admin-stat-icon">✅</div>
                </div>
                <div class="admin-stat-number"><?= $stats['total_settled'] ?></div>
                <span style="font-size:0.75rem; color:var(--text-dim);">Settlements paid</span>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">Payment Apps</span>
                    <div class="admin-stat-icon">📲</div>
                </div>
                <div class="admin-stat-number"><?= $stats['active_apps'] ?> <small style="font-size:1rem; color:var(--text-dim);">/ <?= $stats['total_apps'] ?></small></div>
                <span style="font-size:0.75rem; color:var(--text-dim);">Active UPI deep links</span>
            </div>
        </div>

        <!-- Recent Activity: Latest Expenses Across Groups -->
        <div class="admin-table-container">
            <div class="admin-table-header">
                <div>
                    <h3 style="margin:0; font-size:1.1rem;">⚡ Recent Expenses Logged Across Groups</h3>
                    <small style="color:var(--text-muted);">Real-time group activity (zero extra database logging overhead)</small>
                </div>
                <a href="admin?tab=expenses" class="btn btn-secondary btn-sm" style="font-size:0.78rem;">View All Expenses (<?= $stats['total_expenses'] ?>) →</a>
            </div>

            <?php if (empty($allExpenses)): ?>
                <div style="padding:2rem; text-align:center; color:var(--text-muted);">
                    No expenses recorded yet.
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Expense Title</th>
                                <th>Amount</th>
                                <th>Paid By</th>
                                <th>Added By</th>
                                <th>Expense Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($allExpenses, 0, 15) as $exp): ?>
                                <tr>
                                    <td>
                                        <a href="group?id=<?= $exp['group_id'] ?>" style="font-weight:700; color:var(--color-primary); text-decoration:underline;">
                                            <?= e($exp['group_name']) ?>
                                        </a>
                                    </td>
                                    <td><strong><?= e($exp['title']) ?></strong></td>
                                    <td>
                                        <strong style="color:#059669;"><?= formatMoney($exp['amount'], $exp['group_currency']) ?></strong>
                                    </td>
                                    <td><?= e($exp['payer_name']) ?></td>
                                    <td><span style="color:var(--text-dim);"><?= e($exp['creator_username']) ?></span></td>
                                    <td style="font-size:0.8rem; color:var(--text-dim); white-space:nowrap;">
                                        <?= date('M d, Y', strtotime($exp['expense_date'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 2: PAYMENT APPS (DYNAMIC ADD / REMOVE / HIDE / UNHIDE)        -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($activeTab === 'apps'): ?>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
            <div>
                <h2 style="font-size:1.3rem; margin:0;">💳 Dynamic Payment Apps</h2>
                <p style="color:var(--text-muted); font-size:0.85rem; margin:0.2rem 0 0 0;">
                    Configure deep links (e.g. <code>gpay://</code>, <code>phonepe://</code>, <code>paytmmp://</code>, custom apps) and hide/unhide apps instantly.
                </p>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddAppModal()">
                    ➕ Add Payment App
                </button>
                <a href="admin?action=reset_apps" class="btn btn-secondary btn-sm" onclick="return confirm('Restore all default payment apps? This will overwrite custom app edits.');">
                    🔄 Reset to Defaults
                </a>
            </div>
        </div>

        <div class="admin-table-container">
            <div class="admin-table-header">
                <h3 style="margin:0; font-size:1.05rem;">Configured Payment Applications</h3>
                <small style="color:var(--text-muted);">Active apps will be displayed dynamically in all group and public settlement QR panels</small>
            </div>

            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">Order</th>
                            <th>Preview / Logo</th>
                            <th>App Name</th>
                            <th>App Code</th>
                            <th>URI Scheme / Prefix</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paymentApps)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">
                                    No payment apps found. Click "Reset to Defaults" above to load default UPI apps.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paymentApps as $app): ?>
                                <tr>
                                    <td>
                                        <strong>#<?= (int)$app['sort_order'] ?></strong>
                                    </td>
                                    <td>
                                        <div style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; padding:0.35rem 0.65rem; border-radius:var(--radius-sm); border:1.5px solid var(--border-ink); background:<?= e($app['bg_color']) ?>; color:<?= e($app['text_color']) ?>; min-width:80px;">
                                            <?php if (!empty($app['icon_data'])): ?>
                                                <?= $app['icon_data'] ?>
                                            <?php endif; ?>
                                            <span style="font-weight:700; font-size:0.8rem;"><?= e($app['name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= e($app['name']) ?></strong>
                                    </td>
                                    <td>
                                        <code><?= e($app['app_code']) ?></code>
                                    </td>
                                    <td>
                                        <code style="background:#f1f5f9; padding:0.2rem 0.4rem; border-radius:4px; font-size:0.8rem; color:#0284c7;">
                                            <?= e($app['uri_prefix']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?php if ($app['is_active']): ?>
                                            <span class="status-badge status-active">🟢 Visible</span>
                                        <?php else: ?>
                                            <span class="status-badge status-hidden">🔴 Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <!-- Toggle Hide/Unhide -->
                                        <a href="admin?action=toggle_app&id=<?= $app['id'] ?>" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;">
                                            <?= $app['is_active'] ? '🙈 Hide' : '👁️ Unhide' ?>
                                        </a>

                                        <!-- Edit App -->
                                        <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;"
                                                onclick='openEditAppModal(<?= json_encode($app) ?>)'>
                                            ✏️ Edit
                                        </button>

                                        <!-- Delete App -->
                                        <a href="admin?action=delete_app&id=<?= $app['id'] ?>" class="btn btn-danger btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;"
                                           onclick="return confirm('Delete payment app <?= e($app['name']) ?>?');">
                                            🗑️
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add / Edit Payment App Modal -->
        <div id="appModal" class="admin-modal-backdrop">
            <div class="admin-modal-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1.5px solid var(--border-ink); padding-bottom:0.5rem;">
                    <h3 id="appModalTitle" style="margin:0; font-size:1.2rem;">➕ Add Payment App</h3>
                    <button type="button" onclick="closeAppModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; font-weight:800;">&times;</button>
                </div>

                <form action="admin" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_app">
                    <input type="hidden" name="id" id="app_id" value="">

                    <div class="form-group" style="margin-bottom:0.8rem;">
                        <label class="form-label" style="font-size:0.82rem; font-weight:700;">App Name *</label>
                        <input type="text" name="name" id="app_name" class="form-control" placeholder="e.g. PhonePe or Cred" required>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; margin-bottom:0.8rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-size:0.82rem; font-weight:700;">App Code *</label>
                            <input type="text" name="app_code" id="app_code" class="form-control" placeholder="e.g. phonepe or cred" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-size:0.82rem; font-weight:700;">Sort Order</label>
                            <input type="number" name="sort_order" id="app_sort" class="form-control" value="1">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0.8rem;">
                        <label class="form-label" style="font-size:0.82rem; font-weight:700;">URI Scheme / Deep Link Prefix *</label>
                        <input type="text" name="uri_prefix" id="app_uri" class="form-control" placeholder="e.g. phonepe://pay? or gpay://upi/pay? or upi://pay?" required>
                        <small style="color:var(--text-muted); font-size:0.75rem; display:block; margin-top:0.25rem;">
                            Common schemes: <code>phonepe://pay?</code>, <code>gpay://upi/pay?</code>, <code>paytmmp://pay?</code>, <code>bhim://pay?</code>, <code>upi://pay?</code>
                        </small>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; margin-bottom:0.8rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-size:0.82rem; font-weight:700;">Background Color</label>
                            <input type="color" name="bg_color" id="app_bg" class="form-control" value="#ffffff" style="height:42px; padding:0.2rem;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-size:0.82rem; font-weight:700;">Text Color</label>
                            <input type="color" name="text_color" id="app_text" class="form-control" value="#1f2937" style="height:42px; padding:0.2rem;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0.8rem;">
                        <label class="form-label" style="font-size:0.82rem; font-weight:700;">Icon SVG Code or Emoji</label>
                        <textarea name="icon_data" id="app_icon" class="form-control" rows="3" placeholder="Paste SVG markup (e.g. <svg width='18' height='18' ...>) or enter an emoji"></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom:1.2rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:700; font-size:0.85rem;">
                            <input type="checkbox" name="is_active" id="app_active" value="1" checked style="width:18px; height:18px;">
                            <span>Active / Visible to users</span>
                        </label>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeAppModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Payment App</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openAddAppModal() {
            document.getElementById('appModalTitle').innerText = '➕ Add Payment App';
            document.getElementById('app_id').value = '';
            document.getElementById('app_name').value = '';
            document.getElementById('app_code').value = '';
            document.getElementById('app_uri').value = 'upi://pay?';
            document.getElementById('app_bg').value = '#ffffff';
            document.getElementById('app_text').value = '#1f2937';
            document.getElementById('app_icon').value = '';
            document.getElementById('app_sort').value = '10';
            document.getElementById('app_active').checked = true;
            document.getElementById('appModal').style.display = 'flex';
        }

        function openEditAppModal(app) {
            document.getElementById('appModalTitle').innerText = '✏️ Edit Payment App';
            document.getElementById('app_id').value = app.id;
            document.getElementById('app_name').value = app.name;
            document.getElementById('app_code').value = app.app_code;
            document.getElementById('app_uri').value = app.uri_prefix;
            document.getElementById('app_bg').value = app.bg_color || '#ffffff';
            document.getElementById('app_text').value = app.text_color || '#1f2937';
            document.getElementById('app_icon').value = app.icon_data || '';
            document.getElementById('app_sort').value = app.sort_order;
            document.getElementById('app_active').checked = (parseInt(app.is_active) === 1);
            document.getElementById('appModal').style.display = 'flex';
        }

        function closeAppModal() {
            document.getElementById('appModal').style.display = 'none';
        }
        </script>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 3: USER MANAGEMENT & PASSWORDLESS IMPERSONATION               -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($activeTab === 'users'): ?>
        
        <div style="margin-bottom:1rem;">
            <h2 style="font-size:1.3rem; margin:0;">👥 User Accounts & Access Control</h2>
            <p style="color:var(--text-muted); font-size:0.85rem; margin:0.2rem 0 0 0;">
                Click <strong>"⚡ Login As User"</strong> to instantly access any user's account without a password. Edit credentials, reset passwords, or manage admin permissions.
            </p>
        </div>

        <div class="admin-table-container">
            <div class="admin-table-header">
                <h3 style="margin:0; font-size:1.05rem;">All Registered Users (<?= count($allUsers) ?>)</h3>
            </div>

            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Groups</th>
                            <th>Expenses</th>
                            <th>Joined</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $u): ?>
                            <tr>
                                <td><strong>#<?= $u['id'] ?></strong></td>
                                <td>
                                    <strong><?= e($u['username']) ?></strong>
                                    <?php if ((int)$u['id'] === (int)$currentUser['id']): ?>
                                        <span style="font-size:0.7rem; color:var(--color-primary); font-weight:700;">(You)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="color:var(--text-muted);"><?= e($u['email']) ?></span>
                                </td>
                                <td>
                                    <?php if ($u['is_admin']): ?>
                                        <span class="status-badge status-admin">🛡️ Admin</span>
                                    <?php else: ?>
                                        <span class="status-badge status-user">Member</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= (int)$u['groups_count'] ?></strong> groups
                                </td>
                                <td>
                                    <strong><?= (int)$u['expenses_count'] ?></strong> created
                                </td>
                                <td style="font-size:0.8rem; color:var(--text-dim); white-space:nowrap;">
                                    <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <!-- Passwordless Impersonation ("Login As User") -->
                                    <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                        <a href="admin?action=impersonate&user_id=<?= $u['id'] ?>" 
                                           class="btn btn-primary btn-sm" 
                                           style="font-size:0.75rem; padding:0.25rem 0.6rem; background:#0284c7; border-color:var(--border-ink);"
                                           title="Log in as <?= e($u['username']) ?> without password"
                                           onclick="return confirm('Log in as <?= e($u['username']) ?> without password?');">
                                            ⚡ Login As User
                                        </a>
                                    <?php endif; ?>

                                    <!-- Edit User Details -->
                                    <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;"
                                            onclick='openEditUserModal(<?= json_encode($u) ?>)' title="Edit Details">
                                        ✏️ Edit
                                    </button>

                                    <!-- Change Password -->
                                    <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;"
                                            onclick="openChangePasswordModal(<?= $u['id'] ?>, '<?= e($u['username']) ?>')" title="Change Password">
                                        🔑 Password
                                    </button>

                                    <!-- Delete User -->
                                    <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                        <form action="admin" method="POST" style="display:inline;" onsubmit="return confirm('Delete user <?= e($u['username']) ?> and all their data? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;" title="Delete User">
                                                🗑️
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div id="editUserModal" class="admin-modal-backdrop">
            <div class="admin-modal-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1.5px solid var(--border-ink); padding-bottom:0.5rem;">
                    <h3 style="margin:0; font-size:1.2rem;">✏️ Edit User Details</h3>
                    <button type="button" onclick="closeEditUserModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; font-weight:800;">&times;</button>
                </div>
                <form action="admin" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id" value="">

                    <div class="form-group" style="margin-bottom:0.8rem;">
                        <label class="form-label" style="font-size:0.82rem; font-weight:700;">Username *</label>
                        <input type="text" name="username" id="edit_user_name" class="form-control" required>
                    </div>

                    <div class="form-group" style="margin-bottom:0.8rem;">
                        <label class="form-label" style="font-size:0.82rem; font-weight:700;">Email Address *</label>
                        <input type="email" name="email" id="edit_user_email" class="form-control" required>
                    </div>

                    <div class="form-group" style="margin-bottom:1.2rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:700; font-size:0.85rem;">
                            <input type="checkbox" name="is_admin" id="edit_user_admin" value="1" style="width:18px; height:18px;">
                            <span>Grant Administrator Privileges</span>
                        </label>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Modal -->
        <div id="passwordModal" class="admin-modal-backdrop">
            <div class="admin-modal-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1.5px solid var(--border-ink); padding-bottom:0.5rem;">
                    <h3 style="margin:0; font-size:1.2rem;">🔑 Set New Password</h3>
                    <button type="button" onclick="closeChangePasswordModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; font-weight:800;">&times;</button>
                </div>
                <form action="admin" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="user_id" id="pwd_user_id" value="">

                    <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.8rem;">
                        Setting a new password for <strong id="pwd_user_name_display" style="color:var(--text-main);"></strong>.
                    </p>

                    <div class="form-group" style="margin-bottom:1.2rem;">
                        <label class="form-label" style="font-size:0.82rem; font-weight:700;">New Password *</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Enter at least 6 characters" minlength="6" required>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeChangePasswordModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditUserModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_user_name').value = user.username;
            document.getElementById('edit_user_email').value = user.email;
            document.getElementById('edit_user_admin').checked = (parseInt(user.is_admin) === 1);
            document.getElementById('editUserModal').style.display = 'flex';
        }
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        function openChangePasswordModal(userId, username) {
            document.getElementById('pwd_user_id').value = userId;
            document.getElementById('pwd_user_name_display').innerText = username;
            document.getElementById('passwordModal').style.display = 'flex';
        }
        function closeChangePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }
        </script>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 4: GROUPS MANAGEMENT                                          -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($activeTab === 'groups'): ?>
        
        <div style="margin-bottom:1rem;">
            <h2 style="font-size:1.3rem; margin:0;">📁 All Platform Groups (<?= count($allGroups) ?>)</h2>
            <p style="color:var(--text-muted); font-size:0.85rem; margin:0.2rem 0 0 0;">
                Inspect any group, view share tokens, total participants and total expenses recorded.
            </p>
        </div>

        <div class="admin-table-container">
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Group Name</th>
                            <th>Owner</th>
                            <th>Members</th>
                            <th>Expenses</th>
                            <th>Total Spent</th>
                            <th>Created</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allGroups)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:2rem; color:var(--text-muted);">
                                    No groups created yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allGroups as $g): ?>
                                <tr>
                                    <td><strong>#<?= $g['id'] ?></strong></td>
                                    <td>
                                        <strong><?= e($g['name']) ?></strong>
                                        <?php if (!empty($g['upi_id'])): ?>
                                            <span style="font-size:0.72rem; color:#059669; display:block;">💳 <?= e($g['upi_id']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= e($g['owner_username']) ?></strong>
                                        <small style="display:block; color:var(--text-dim);"><?= e($g['owner_email']) ?></small>
                                    </td>
                                    <td><strong><?= (int)$g['participants_count'] ?></strong></td>
                                    <td><strong><?= (int)$g['expenses_count'] ?></strong></td>
                                    <td>
                                        <strong style="color:#10b981;"><?= formatMoney($g['total_spent'], $g['currency']) ?></strong>
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--text-dim); white-space:nowrap;">
                                        <?= date('M d, Y', strtotime($g['created_at'])) ?>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <a href="group?id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;" target="_blank">
                                            👁️ Open Group
                                        </a>
                                        <a href="public?token=<?= e($g['share_token']) ?>" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;" target="_blank">
                                            🔗 Public Link
                                        </a>
                                        <form action="admin" method="POST" style="display:inline;" onsubmit="return confirm('Delete group <?= e($g['name']) ?> and all expenses?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete_group">
                                            <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;">
                                                🗑️
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 5: EXPENSES MANAGEMENT                                        -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($activeTab === 'expenses'): ?>
        
        <div style="margin-bottom:1rem;">
            <h2 style="font-size:1.3rem; margin:0;">🧾 All Platform Expenses</h2>
            <p style="color:var(--text-muted); font-size:0.85rem; margin:0.2rem 0 0 0;">
                Showing recent expenses added across all groups on the platform.
            </p>
        </div>

        <div class="admin-table-container">
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Group</th>
                            <th>Title</th>
                            <th>Amount</th>
                            <th>Paid By</th>
                            <th>Created By</th>
                            <th>Expense Date</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allExpenses)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:2rem; color:var(--text-muted);">
                                    No expenses logged yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allExpenses as $exp): ?>
                                <tr>
                                    <td><strong>#<?= $exp['id'] ?></strong></td>
                                    <td>
                                        <a href="group?id=<?= $exp['group_id'] ?>" style="font-weight:700; color:var(--color-primary); text-decoration:underline;">
                                            <?= e($exp['group_name']) ?>
                                        </a>
                                    </td>
                                    <td><strong><?= e($exp['title']) ?></strong></td>
                                    <td>
                                        <strong style="color:#059669;"><?= formatMoney($exp['amount'], $exp['group_currency']) ?></strong>
                                    </td>
                                    <td><?= e($exp['payer_name']) ?></td>
                                    <td>
                                        <span style="color:var(--text-dim);"><?= e($exp['creator_username']) ?></span>
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--text-dim); white-space:nowrap;">
                                        <?= date('M d, Y', strtotime($exp['expense_date'])) ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="group?id=<?= $exp['group_id'] ?>" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.5rem;">
                                            View in Group
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
