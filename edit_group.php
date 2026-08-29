<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$groupId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (!isGroupOwner($groupId, $currentUser['id'])) {
    header("Location: group?id=" . $groupId . "&error=" . urlencode("Only owner can edit group details."));
    exit;
}

$group = getGroupById($groupId);
if (!$group) {
    header("Location: dashboard");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $currency = trim($_POST['currency'] ?? '₹');

        if (empty($name)) {
            $error = 'Group name is required.';
        } else {
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE groups SET name = :name, description = :description, currency = :currency WHERE id = :id AND owner_id = :owner_id");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':currency' => $currency,
                ':id' => $groupId,
                ':owner_id' => $currentUser['id']
            ]);

            header("Location: group?id=" . $groupId . "&msg=" . urlencode("Group details updated."));
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container" style="max-width: 520px;">
    <div class="card">
        <h2 class="card-title">Edit Group Details</h2>
        <p class="card-subtitle">Update group name, currency, or description.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <span>⚠️</span> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="edit_group" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= $groupId ?>">

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Group Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? $group['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Currency *</label>
                    <select name="currency" class="form-control" required>
                        <?php foreach (SUPPORTED_CURRENCIES as $symbol => $label): ?>
                            <option value="<?= e($symbol) ?>" <?= (($_POST['currency'] ?? $group['currency']) === $symbol) ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? $group['description']) ?></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <a href="group?id=<?= $groupId ?>" class="btn btn-secondary" style="flex: 1;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 2;">
                    <span>💾</span> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
