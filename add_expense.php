<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$groupId = (int) ($_GET['group_id'] ?? $_POST['group_id'] ?? 0);
$group = getGroupById($groupId);

if (!$group) {
    header("Location: dashboard?msg=" . urlencode("Group not found."));
    exit;
}

$participants = getGroupParticipants($groupId);
if (empty($participants)) {
    header("Location: group?id=" . $groupId . "&error=" . urlencode("Group has no participants. Add participants first."));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF security token.';
    } else {
        $title = $_POST['title'] ?? '';
        $amount = (float) ($_POST['amount'] ?? 0);
        $payerId = (int) ($_POST['payer_id'] ?? 0);
        $splitIds = $_POST['split_participants'] ?? [];
        $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');

        if (empty(trim($title))) {
            $error = 'Please enter an expense title.';
        } elseif ($amount <= 0) {
            $error = 'Please enter a valid amount greater than 0.';
        } elseif (empty($payerId)) {
            $error = 'Please select who paid for this expense.';
        } elseif (empty($splitIds)) {
            $error = 'Please select at least one participant to split the expense between.';
        } else {
            $success = addExpense($groupId, $title, $amount, $payerId, $splitIds, $expenseDate, $currentUser['id']);
            if ($success) {
                header("Location: group?id=" . $groupId . "&msg=" . urlencode("Expense '" . $title . "' added successfully."));
                exit;
            } else {
                $error = 'Failed to add expense. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container" style="max-width: 600px;">
    <div class="card">
        <h2 class="card-title">Add New Expense</h2>
        <p class="card-subtitle">Group: <strong><?= e($group['name']) ?></strong></p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <span>⚠️</span> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="add_expense" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="group_id" value="<?= $groupId ?>">

            <div class="form-group">
                <label class="form-label">Expense Title *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Dinner at Bistro, Grocery Shopping, Taxi fare" value="<?= e($_POST['title'] ?? '') ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Amount (<?= e($group['currency']) ?>) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" value="<?= e($_POST['amount'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Expense Date</label>
                    <input type="date" name="expense_date" class="form-control" value="<?= e($_POST['expense_date'] ?? date('Y-m-d')) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Who Paid? *</label>
                <select name="payer_id" class="form-control" required>
                    <option value="">-- Select Participant Who Paid --</option>
                    <?php foreach ($participants as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (isset($_POST['payer_id']) && (int)$_POST['payer_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                            <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <label class="form-label" style="margin: 0;">Split Between Selected Participants *</label>
                    <button type="button" id="selectAllParticipants" class="btn btn-secondary btn-sm" style="padding: 0.2rem 0.6rem; font-size: 0.75rem;">Deselect All</button>
                </div>

                <div class="checkbox-grid">
                    <?php foreach ($participants as $p): ?>
                        <label class="checkbox-card">
                            <input type="checkbox" name="split_participants[]" value="<?= (int)$p['id'] ?>" class="split-checkbox" checked>
                            <span><?= e($p['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <a href="group?id=<?= $groupId ?>" class="btn btn-secondary" style="flex: 1;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 2;">
                    <span>💾</span> Save Expense
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
