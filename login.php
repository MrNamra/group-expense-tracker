<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard");
    exit;
}

$error = '';
$msg = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF security token.';
    } else {
        $input = $_POST['input'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = loginUser($input, $password);
        if ($result['success']) {
            header("Location: dashboard");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="card">
        <h2 class="card-title">Welcome Back</h2>
        <p class="card-subtitle">Log in to manage your expense groups and balances.</p>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-info">
                <span>ℹ️</span> <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <span>⚠️</span> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="login" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="form-group">
                <label class="form-label">Username or Email</label>
                <input type="text" name="input" class="form-control" placeholder="Enter username or email" value="<?= e($_POST['input'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                <span>🔑</span> Log In
            </button>
        </form>

        <p style="margin-top: 1.5rem; text-align: center; font-size: 0.875rem; color: var(--text-muted);">
            Don't have an account? <a href="register" style="color: #6366f1; font-weight: 600;">Create one here</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
