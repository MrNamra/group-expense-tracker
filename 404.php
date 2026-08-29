<?php
http_response_code(404);
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container" style="max-width: 520px; text-align: center; margin: 4rem auto;">
    <div class="card" style="padding: 3rem 2rem;">
        <div style="font-size: 4rem; margin-bottom: 1rem; filter: drop-shadow(0 0 15px rgba(239, 68, 68, 0.4));">🔍 404</div>
        <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem; background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Page Not Found</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; margin-bottom: 2rem;">
            Oops! The page or group you are looking for does not exist, has been deleted, or the share URL is invalid.
        </p>
        
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <a href="dashboard" class="btn btn-primary">
                <span>🏠</span> Return to Dashboard
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
