<?php
/**
 * SplitWise PRO - Forgot Password Request
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

if (isLoggedIn()) {
    header("Location: dashboard");
    exit;
}

$error = '';
$success = '';
$resetUrl = null;
$usernameFound = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF security token. Please refresh and try again.';
    } else {
        $input = trim($_POST['input'] ?? '');
        $ip = getSecureClientIP();

        $res = createPasswordResetRequest($input, $ip);
        if ($res['success']) {
            $success = $res['message'];
            if (!empty($res['reset_url'])) {
                $resetUrl = $res['reset_url'];
                $usernameFound = $res['user']['username'] ?? '';
            }
        } else {
            $error = $res['message'];
        }
    }
}

$pageTitle = "Forgot Password";
$pageDescription = "Reset your SplitWise PRO account password securely.";

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container" style="max-width: 480px; margin: 2.5rem auto; padding: 0 1rem;">
    <div class="card" style="padding: 2.2rem;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">🔐</div>
            <h1 class="card-title" style="font-size: 1.7rem; margin: 0 0 0.4rem 0;">Reset Password</h1>
            <p class="card-subtitle" style="margin: 0;">Enter your username or email address and we'll help you regain access.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                <span>⚠️</span> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom: 1.25rem;">
                <div>
                    <strong>✅ Request Processed</strong>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.88rem;"><?= e($success) ?></p>
                </div>
            </div>

            <?php if (!empty($resetUrl)): ?>
                <div style="background: #fdf4ff; border: 2px solid #d946ef; border-radius: var(--radius-md); padding: 1.2rem; margin-bottom: 1.5rem; box-shadow: 2px 2px 0px var(--border-ink);">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem;">
                        <span style="font-size: 1.2rem;">⚡</span>
                        <strong style="color: #86198f; font-size: 0.95rem;">One-Click Direct Recovery Link:</strong>
                    </div>
                    <p style="font-size: 0.82rem; color: #701a75; margin: 0 0 0.8rem 0;">
                        Account: <strong><?= e($usernameFound) ?></strong>. This secure recovery token is valid for 1 hour.
                    </p>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="<?= e($resetUrl) ?>" class="btn btn-primary" style="flex: 1; text-align: center; justify-content: center; font-size: 0.9rem; padding: 0.65rem 1rem;">
                            🚀 Set New Password Now
                        </a>
                        <button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText('<?= e($resetUrl) ?>'); alert('Reset link copied to clipboard!');" style="padding: 0.65rem 0.9rem;" title="Copy Link">
                            📋 Copy
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form action="forgot_password" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="font-weight: 700;">Username or Email Address</label>
                <input type="text" name="input" class="form-control" placeholder="e.g. johndoe or john@example.com" value="<?= e($_POST['input'] ?? '') ?>" required autofocus>
                <small style="color: var(--text-muted); font-size: 0.78rem; display: block; margin-top: 0.35rem;">
                    🛡️ Protected by rate limiting (max 3 requests per 15 mins).
                </small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1rem; padding: 0.75rem;">
                <span>🔑</span> Generate Reset Link
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.88rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
            <a href="login" style="color: var(--color-primary); font-weight: 700;">← Back to Login</a>
            <a href="register" style="color: var(--text-muted); font-weight: 600;">Create Account</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
