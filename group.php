<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$groupId = (int) ($_GET['id'] ?? 0);
$group = getGroupById($groupId);

if (!$group) {
    header("Location: dashboard?msg=" . urlencode("Group not found."));
    exit;
}

$currentUser = getCurrentUser();
$isOwner = $currentUser && isGroupOwner($groupId, $currentUser['id']);
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

$participants = getGroupParticipants($groupId);
$expenses = getGroupExpenses($groupId);
$stats = calculateGroupStats($groupId);

$publicUrl = APP_URL . "/public?token=" . urlencode($group['share_token']);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Group Header Banner -->
<div class="group-header-banner">
    <div class="banner-top">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.4rem;">
                <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0;"><?= e($group['name']) ?></h1>
                <?php if ($isOwner): ?>
                    <span class="badge-owner">You are Owner</span>
                <?php endif; ?>
            </div>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.5rem;"><?= e($group['description'] ?: 'No description.') ?></p>
            <small style="color: var(--text-dim);">Created by <strong><?= e($group['owner_name']) ?></strong> on <?= date('M d, Y', strtotime($group['created_at'])) ?></small>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.75rem; align-items: flex-end;">
            <!-- Unique Shareable URL Box -->
            <div class="share-box">
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">Share URL:</span>
                <input type="text" id="shareUrlInput" class="share-input" value="<?= e($publicUrl) ?>" readonly>
                <button type="button" id="copyShareUrlBtn" class="btn btn-secondary btn-sm">
                    📋 Copy
                </button>
            </div>

            <?php if ($isOwner): ?>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="edit_group?id=<?= $groupId ?>" class="btn btn-secondary btn-sm">✏️ Edit Group</a>
                    <a href="delete_group?id=<?= $groupId ?>&csrf_token=<?= e($csrfToken) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this group and all its expenses?');">🗑️ Delete Group</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success">
        <span>✅</span> <?= e($msg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <span>⚠️</span> <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="group-content-grid">
    <!-- Left Main Column: Expenses -->
    <div>
        <div class="page-header" style="margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.35rem; font-weight: 700;">Group Expenses</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted);"><?= count($expenses) ?> expenses logged</p>
            </div>

            <?php if (isLoggedIn()): ?>
                <a href="add_expense?group_id=<?= $groupId ?>" class="btn btn-primary btn-sm">
                    <span>➕</span> Add New Expense
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($expenses)): ?>
            <div class="card" style="text-align: center; padding: 3rem 1.5rem; margin-bottom: 2rem;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🧾</div>
                <h4 style="margin-bottom: 0.4rem;">No Expenses Added Yet</h4>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">Start adding bill amounts, specifying who paid and who shares the expense.</p>
                <?php if (isLoggedIn()): ?>
                    <a href="add_expense?group_id=<?= $groupId ?>" class="btn btn-primary btn-sm">
                        <span>➕</span> Add First Expense
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($expenses as $exp): ?>
                <div class="expense-item">
                    <div class="expense-info">
                        <div class="expense-date-badge">
                            <div class="date-day"><?= date('d', strtotime($exp['expense_date'])) ?></div>
                            <div class="date-month"><?= date('M', strtotime($exp['expense_date'])) ?></div>
                        </div>

                        <div>
                            <h3 class="expense-title"><?= e($exp['title']) ?></h3>
                            <div class="expense-sub">
                                Paid by <strong><?= e($exp['payer_name']) ?></strong> • 
                                Shared between: 
                                <em>
                                    <?= implode(', ', array_map(fn($sd) => e($sd['participant_name']), $exp['split_details'])) ?>
                                </em>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 1.25rem;">
                        <div class="expense-amount">
                            <?= formatMoney($exp['amount'], $group['currency']) ?>
                        </div>

                        <?php if ($isOwner): ?>
                            <div class="expense-actions">
                                <a href="edit_expense?id=<?= $exp['id'] ?>&group_id=<?= $groupId ?>" class="btn btn-secondary btn-icon btn-sm" title="Edit Expense">
                                    ✏️
                                </a>
                                <a href="delete_expense?id=<?= $exp['id'] ?>&group_id=<?= $groupId ?>&csrf_token=<?= e($csrfToken) ?>" class="btn btn-danger btn-icon btn-sm" title="Delete Expense" onclick="return confirm('Delete this expense?');">
                                    🗑️
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Participants Management Box -->
        <div class="card" style="margin-top: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.15rem; font-weight: 700;">Group Participants (<?= count($participants) ?>)</h3>
            </div>

            <?php if (isLoggedIn()): ?>
                <form action="add_participant" method="POST" style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem;">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="group_id" value="<?= $groupId ?>">
                    <input type="text" name="name" class="form-control" placeholder="Enter new participant name..." required style="flex: 1;">
                    <button type="submit" class="btn btn-secondary btn-sm">➕ Add</button>
                </form>
            <?php endif; ?>

            <div style="display: flex; flex-wrap: wrap; gap: 0.6rem;">
                <?php foreach ($participants as $p): ?>
                    <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); border-radius: var(--radius-full); padding: 0.4rem 0.9rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                        <span>👤 <?= e($p['name']) ?></span>
                        <?php if ($isOwner && count($participants) > 1): ?>
                            <a href="delete_participant?id=<?= $p['id'] ?>&group_id=<?= $groupId ?>&csrf_token=<?= e($csrfToken) ?>" style="color: #f87171; margin-left: 0.2rem;" onclick="return confirm('Remove participant <?= e($p['name']) ?>?');" title="Remove participant">&times;</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Summary & Debt Settlement Stats -->
    <div>
        <!-- Total Group Stats -->
        <div class="settlement-card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-muted);">
                📊 Expense Summary
            </h3>
            
            <div style="margin-bottom: 1.25rem;">
                <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px;">Total Spent</div>
                <div style="font-size: 1.8rem; font-weight: 800; background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <?= formatMoney($stats['total_expense'], $group['currency']) ?>
                </div>
            </div>

            <!-- Settlement Plan: Who pays whom -->
            <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                <span>🤝</span> Final Settle Up Plan
            </h4>

            <?php if (empty($stats['settlements'])): ?>
                <div style="font-size: 0.85rem; color: #34d399; background: rgba(16, 185, 129, 0.1); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid rgba(16, 185, 129, 0.2); text-align: center;">
                    ✨ Everyone is all settled up! No pending payments.
                </div>
            <?php else: ?>
                <?php foreach ($stats['settlements'] as $st): ?>
                    <div class="settlement-item">
                        <div>
                            <strong><?= e($st['from']) ?></strong>
                            <span class="settlement-arrow"> ➔ pays </span>
                            <strong><?= e($st['to']) ?></strong>
                        </div>
                        <div class="settlement-amount">
                            <?= formatMoney($st['amount'], $group['currency']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Individual Balances -->
        <div class="settlement-card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-muted);">
                👤 Individual Breakdown
            </h3>

            <table class="balance-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Paid</th>
                        <th>Share</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['individual_balances'] as $ib): ?>
                        <tr>
                            <td><strong><?= e($ib['name']) ?></strong></td>
                            <td><?= formatMoney($ib['paid'], $group['currency']) ?></td>
                            <td><?= formatMoney($ib['share'], $group['currency']) ?></td>
                            <td>
                                <?php if ($ib['net'] > 0): ?>
                                    <span class="net-positive">+<?= formatMoney($ib['net'], $group['currency']) ?></span>
                                <?php elseif ($ib['net'] < 0): ?>
                                    <span class="net-negative">-<?= formatMoney(abs($ib['net']), $group['currency']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-dim);"><?= formatMoney(0, $group['currency']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
