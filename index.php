<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$groups = getUserGroups($currentUser['id']);
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Expense Groups</h1>
        <p style="color: var(--text-muted); margin-top: 0.25rem;">Manage your group expenses, split bills, and track settlements.</p>
    </div>
    <div>
        <a href="create_group" class="btn btn-primary">
            <span>➕</span> Create New Group
        </a>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success">
        <span>✅</span> <?= e($msg) ?>
    </div>
<?php endif; ?>

<?php if (empty($groups)): ?>
    <div class="card" style="text-align: center; padding: 4rem 2rem;">
        <div style="font-size: 3.5rem; margin-bottom: 1rem;">💸</div>
        <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem;">No Groups Found</h3>
        <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 1.5rem auto;">
            You haven't created or joined any expense groups yet. Create a group to start tracking expenses with friends or colleagues.
        </p>
        <a href="create_group" class="btn btn-primary">
            <span>✨</span> Create Your First Group
        </a>
    </div>
<?php else: ?>
    <div class="groups-grid">
        <?php foreach ($groups as $g): ?>
            <?php $isOwner = ((int)$g['owner_id'] === (int)$currentUser['id']); ?>
            <div class="group-card">
                <div class="group-card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <h2 class="group-title"><?= e($g['name']) ?></h2>
                        <?php if ($isOwner): ?>
                            <span class="badge-owner">Owner</span>
                        <?php endif; ?>
                    </div>
                    <p class="group-desc"><?= e($g['description'] ?: 'No description provided.') ?></p>
                </div>

                <div class="group-stats-row">
                    <div class="stat-item">
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-value"><?= formatMoney($g['total_spent'], $g['currency']) ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Expenses</div>
                        <div class="stat-value"><?= (int)$g['total_expenses_count'] ?></div>
                    </div>
                </div>

                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <a href="group?id=<?= (int)$g['id'] ?>" class="btn btn-primary btn-sm" style="flex: 1;">
                        <span>👁️</span> View Group
                    </a>
                    <a href="public?token=<?= e($g['share_token']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Open Public Link">
                        <span>🔗</span> Public
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
