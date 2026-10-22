<?php
/**
 * SplitWise PRO - Set New Password
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

if (isLoggedIn()) {
    header("Location: dashboard");
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenInfo = verifyPasswordResetToken($token);

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF security token. Please try again.';
    } elseif (!$tokenInfo) {
        $error = 'This reset link has expired or is invalid. Please request a new one.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match. Please re-enter.';
        } else {
            $res = completePasswordReset($token, $password);
            if ($res['success']) {
                header("Location: login?msg=" . urlencode("Password reset successfully! You can now log in with your new password."));
                exit;
            } else {
                $error = $res['message'];
            }
        }
    }
}

$pageTitle = "Set New Password";
$pageDescription = "Create a new secure password for your SplitWise PRO account.";

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container" style="max-width: 480px; margin: 2.5rem auto; padding: 0 1rem;">
    <div class="card" style="padding: 2.2rem;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">🔑</div>
            <h1 class="card-title" style="font-size: 1.7rem; margin: 0 0 0.4rem 0;">Set New Password</h1>
            <?php if ($tokenInfo): ?>
                <p class="card-subtitle" style="margin: 0;">
                    Resetting password for <strong><?= e($tokenInfo['username']) ?></strong> (<?= e($tokenInfo['email']) ?>)
                </p>
            <?php else: ?>
                <p class="card-subtitle" style="margin: 0; color: #ef4444;">Invalid or Expired Link</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                <span>⚠️</span> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$tokenInfo): ?>
            <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
                <div>
                    <strong>Link Expired or Already Used</strong>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.88rem;">
                        This password reset token has already been used or has expired (tokens are valid for 1 hour).
                    </p>
                </div>
            </div>
            <a href="forgot_password" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center;">
                🔄 Request New Reset Link
            </a>
        <?php else: ?>
            <form action="reset_password" method="POST" id="resetPasswordForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 700;">New Password</label>
                    <input type="password" name="password" id="new_password" class="form-control" placeholder="At least 6 characters" minlength="6" required autofocus>
                    <div id="pwd-strength-bar" style="height: 5px; background: #e2e8f0; border-radius: 3px; margin-top: 0.4rem; overflow: hidden;">
                        <div id="pwd-strength-fill" style="height: 100%; width: 0; transition: width 0.3s, background-color 0.3s;"></div>
                    </div>
                    <small id="pwd-strength-text" style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                        Enter at least 6 characters
                    </small>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 700;">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter new password" minlength="6" required>
                    <small id="pwd-match-text" style="font-size: 0.75rem; display: block; margin-top: 0.25rem;"></small>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn" style="width: 100%; font-size: 1rem; padding: 0.75rem;">
                    <span>🔒</span> Save New Password
                </button>
            </form>
        <?php endif; ?>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.88rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
            <a href="login" style="color: var(--color-primary); font-weight: 700;">← Back to Login</a>
        </div>
    </div>
</div>

<script>
const pwdInput = document.getElementById('new_password');
const confirmInput = document.getElementById('confirm_password');
const fill = document.getElementById('pwd-strength-fill');
const txt = document.getElementById('pwd-strength-text');
const matchTxt = document.getElementById('pwd-match-text');

if (pwdInput) {
    pwdInput.addEventListener('input', () => {
        const val = pwdInput.value;
        let score = 0;
        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        if (val.length === 0) {
            fill.style.width = '0%';
            txt.innerText = 'Enter at least 6 characters';
            txt.style.color = 'var(--text-muted)';
        } else if (val.length < 6) {
            fill.style.width = '20%';
            fill.style.backgroundColor = '#ef4444';
            txt.innerText = 'Too short (min 6 characters)';
            txt.style.color = '#ef4444';
        } else if (score <= 2) {
            fill.style.width = '40%';
            fill.style.backgroundColor = '#f59e0b';
            txt.innerText = 'Fair password';
            txt.style.color = '#f59e0b';
        } else if (score <= 4) {
            fill.style.width = '75%';
            fill.style.backgroundColor = '#3b82f6';
            txt.innerText = 'Good password';
            txt.style.color = '#3b82f6';
        } else {
            fill.style.width = '100%';
            fill.style.backgroundColor = '#10b981';
            txt.innerText = 'Strong password! 🛡️';
            txt.style.color = '#10b981';
        }
        checkMatch();
    });

    confirmInput.addEventListener('input', checkMatch);

    function checkMatch() {
        if (!confirmInput.value) {
            matchTxt.innerText = '';
            return;
        }
        if (pwdInput.value === confirmInput.value) {
            matchTxt.innerText = '✓ Passwords match';
            matchTxt.style.color = '#10b981';
        } else {
            matchTxt.innerText = '✗ Passwords do not match';
            matchTxt.style.color = '#ef4444';
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
