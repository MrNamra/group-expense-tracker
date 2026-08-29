<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';
$group = getGroupByToken($token);

if (!$group) {
    require __DIR__ . '/404.php';
    exit;
}

$groupId = (int)$group['id'];
$participants = getGroupParticipants($groupId);
$expenses = getGroupExpenses($groupId);
$stats = calculateGroupStats($groupId);

$pageTitle = $group['name'] . " (Public View)";
$pageDescription = "Public shared view for " . $group['name'] . " expenses, individual balance summaries, and settlement calculations.";

require_once __DIR__ . '/includes/header.php';
?>

<div class="group-header-banner">
    <div class="banner-top">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.4rem;">
                <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0;"><?= e($group['name']) ?></h1>
                <span class="badge-owner" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border-color: rgba(59, 130, 246, 0.4);">Public View Link</span>
            </div>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.5rem;"><?= e($group['description'] ?: 'No description.') ?></p>
            <small style="color: var(--text-dim);">Created by <strong><?= e($group['owner_name']) ?></strong></small>
        </div>

        <?php if ($isOwner): ?>
            <a href="group?id=<?= $groupId ?>" class="btn btn-primary btn-sm">
                ⚙️ Owner Workspace
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="group-content-grid">
    <!-- Left Main Column: Expenses -->
    <div>
        <div class="page-header" style="margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.35rem; font-weight: 700;">Expenses Breakdown</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted);"><?= count($expenses) ?> recorded expenses</p>
            </div>
        </div>

        <?php if (empty($expenses)): ?>
            <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🧾</div>
                <h4>No Expenses Recorded</h4>
                <p style="color: var(--text-muted); font-size: 0.9rem;">No expenses have been added to this group yet.</p>
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

                    <div class="expense-amount">
                        <?= formatMoney($exp['amount'], $group['currency']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Right Column: Summary & Debt Settlement Stats -->
    <div>
        <!-- Total Group Stats -->
        <div class="settlement-card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-muted);">
                📊 Total Group Expense
            </h3>
            
            <div style="margin-bottom: 1.25rem;">
                <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px;">Overall Spent</div>
                <div style="font-size: 1.8rem; font-weight: 800; background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <?= formatMoney($stats['total_expense'], $group['currency']) ?>
                </div>
            </div>

            <!-- Settlement Plan: Who pays whom -->
            <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                <span>🤝</span> Who Pays Whom & How Much
            </h4>

            <?php if (empty($stats['settlements'])): ?>
                <div style="font-size: 0.85rem; color: #34d399; background: rgba(16, 185, 129, 0.1); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid rgba(16, 185, 129, 0.2); text-align: center;">
                    ✨ Everyone is settled up!
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
                👤 Individual Expense Summary
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
