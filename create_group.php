<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $participants = $_POST['participants'] ?? [];
        $currency = $_POST['currency'] ?? '₹';

        if (empty(trim($name))) {
            $error = 'Group name is required.';
        } else {
            try {
                $groupId = createGroup($currentUser['id'], $name, $description, $participants, $currency);
                header("Location: group?id=" . $groupId . "&msg=" . urlencode("Group created successfully! You can now start adding expenses."));
                exit;
            } catch (Exception $e) {
                $error = 'Failed to create group: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container" style="max-width: 580px;">
    <div class="card">
        <h2 class="card-title">Create New Expense Group</h2>
        <p class="card-subtitle">Set up a group and add participant names to split bills seamlessly.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <span>⚠️</span> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="create_group" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Group Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Summer Beach Trip 2026, Housemate Rent" value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Currency *</label>
                    <select name="currency" class="form-control" required>
                        <?php foreach (SUPPORTED_CURRENCIES as $symbol => $label): ?>
                            <option value="<?= e($symbol) ?>" <?= (($_POST['currency'] ?? '₹') === $symbol) ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description (Optional)</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Brief note about what this group is for..."><?= e($_POST['description'] ?? '') ?></textarea>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">

            <div class="form-group">
                <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Add Group Participants</span>
                    <small style="color: var(--text-dim);">You are automatically included (<?= e($currentUser['username']) ?>)</small>
                </label>

                <div id="participantInputsContainer">
                    <div class="form-group">
                        <input type="text" name="participants[]" class="form-control" placeholder="Participant #1 Name (e.g. Alice)" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="participants[]" class="form-control" placeholder="Participant #2 Name (e.g. Bob)">
                    </div>
                </div>

                <button type="button" id="addMoreParticipantBtn" class="btn btn-secondary btn-sm" style="margin-top: 0.5rem;">
                    <span>➕</span> Add More Person
                </button>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <a href="dashboard" class="btn btn-secondary" style="flex: 1;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 2;">
                    <span>✨</span> Create Group
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
