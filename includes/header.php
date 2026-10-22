<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

// Apply HTTP security headers & global request throttling
applySecurityHeaders();
enforceGlobalRateLimit();

$currentUser = getCurrentUser();
$csrfToken = generateCSRFToken();

$title    = isset($pageTitle)       ? $pageTitle . ' | ' . APP_NAME : APP_NAME . ' — Free Group Expense Tracker & Bill Splitter';
$metaDesc = isset($pageDescription) ? $pageDescription : 'SplitWise PRO lets you track shared expenses, split bills instantly, calculate who owes what, and settle debts with a single shareable link. 100% free.';
$metaKW   = isset($pageKeywords)    ? $pageKeywords    : 'expense tracker, bill splitter, group expenses, split bills, money manager, shared expenses, settling up, splitwise, expense sharing';

// Always use HTTPS canonical from APP_URL, not the raw server variable
$canonicalBase = rtrim(APP_URL, '/');
$path          = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$canonicalUrl  = $canonicalBase . $path;

$ogImage = $canonicalBase . '/og-image.png';

// Dynamic base path for PWA assets to work smoothly across domain roots and subfolders
$appBasePath   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$manifestHref  = ($appBasePath !== '' ? $appBasePath : '') . '/manifest.webmanifest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <!-- ── Progressive Web App (PWA) ── -->
    <link rel="manifest" href="<?= $manifestHref ?>" crossorigin="use-credentials">
    <meta name="theme-color" content="#8b5cf6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SplitWise PRO">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <script src="js/pwa.js" defer></script>

    <!-- ── Primary SEO Meta Tags ── -->
    <title><?= e($title) ?></title>
    <meta name="title"       content="<?= e($title) ?>">
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="keywords"    content="<?= e($metaKW) ?>">
    <meta name="author"      content="SplitWise PRO">
    <meta name="robots"      content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="language"    content="English">
    <meta name="revisit-after" content="7 days">

    <!-- ── Canonical URL ── -->
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">

    <!-- ── Open Graph / Facebook / WhatsApp ── -->
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="<?= e($canonicalUrl) ?>">
    <meta property="og:site_name"   content="SplitWise PRO">
    <meta property="og:title"       content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:image"       content="<?= e($ogImage) ?>">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale"      content="en_IN">

    <!-- ── Twitter / X Cards ── -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:url"         content="<?= e($canonicalUrl) ?>">
    <meta name="twitter:title"       content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($metaDesc) ?>">
    <meta name="twitter:image"       content="<?= e($ogImage) ?>">

    <!-- ── Preconnect & Fast Fonts (Non-blocking for slow networks) ── -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap">
    </noscript>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>💸</text></svg>">

    <!-- ── JSON-LD Structured Data ── -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "SplitWise PRO",
      "url": "<?= $canonicalBase ?>",
      "applicationCategory": "FinanceApplication",
      "operatingSystem": "All",
      "browserRequirements": "Requires JavaScript",
      "description": "SplitWise PRO is a free group expense tracker that helps you split bills, calculate settlements, and share expense summaries. Track who paid what and settle debts instantly.",
      "inLanguage": "en",
      "isAccessibleForFree": true,
      "offers": {
        "@type": "Offer",
        "price": "0.00",
        "priceCurrency": "INR"
      },
      "creator": {
        "@type": "Organization",
        "name": "SplitWise PRO",
        "url": "<?= $canonicalBase ?>"
      }
    }
    </script>
</head>
<body>

<header class="navbar">
    <div class="nav-container">
        <a href="dashboard" class="brand-logo" id="nav-brand-logo">
            <div class="brand-icon">💸</div>
            <span>SplitWise <strong style="font-weight: 400; font-size: 0.85em; opacity: 0.8;">PRO</strong></span>
        </a>

        <nav class="nav-links" aria-label="Main Navigation">
            <?php if (isLoggedIn()): ?>
                <div class="user-badge" id="user-profile-badge">
                    <span>👤</span>
                    <span><strong><?= e($currentUser['username']) ?></strong></span>
                    <?php if (isAdmin()): ?>
                        <span style="background:#7c3aed; color:#fff; font-size:0.68rem; font-weight:800; padding:0.1rem 0.35rem; border-radius:4px; margin-left:0.25rem;">ADMIN</span>
                    <?php endif; ?>
                </div>
                <a href="dashboard" class="btn btn-secondary btn-sm" id="nav-link-dashboard">Dashboard</a>
                <a href="contacts" class="btn btn-secondary btn-sm" id="nav-link-contacts">👥 Friends</a>
                <?php if (isAdmin()): ?>
                    <a href="admin" class="btn btn-primary btn-sm" id="nav-link-admin" style="background:#6d28d9; border-color:var(--border-ink);">🛡️ Admin</a>
                <?php endif; ?>
                <a href="logout" class="btn btn-danger btn-sm" id="nav-link-logout">Logout</a>
            <?php else: ?>
                <a href="login" class="btn btn-secondary btn-sm" id="nav-link-login">Login</a>
                <a href="register" class="btn btn-primary btn-sm" id="nav-link-register">Register</a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm" id="pwa-install-btn" style="display:none; background:#ecfdf5; border-color:#10b981; color:#059669; font-weight:700;" onclick="installPWA()" title="Install App to your device">
                📲 Install App
            </button>
        </nav>
    </div>
</header>

<!-- Floating PWA Install Toast for Mobile/Desktop -->
<div id="pwa-install-banner" style="display:none; position:fixed; bottom:20px; left:50%; transform:translateX(-50%); z-index:9999; background:#ffffff; border:2.5px solid var(--border-ink); border-radius:var(--radius-md); box-shadow:4px 4px 0px var(--border-ink); padding:0.75rem 1rem; align-items:center; gap:0.75rem; max-width:92%; width:420px;">
    <img src="icons/icon-192.png" alt="SplitWise PRO" style="width:38px; height:38px; border-radius:8px; border:1.5px solid var(--border-ink);">
    <div style="flex:1;">
        <div style="font-weight:800; font-size:0.88rem; color:var(--text-main);">Install SplitWise PRO</div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Install for instant offline bill splitting</div>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="installPWA()" style="padding:0.35rem 0.75rem; font-size:0.8rem; background:#10b981; border-color:var(--border-ink);">Install</button>
    <button type="button" onclick="dismissPWABanner()" style="background:none; border:none; cursor:pointer; font-size:1.1rem; color:var(--text-muted); padding:0.2rem;" aria-label="Dismiss">✕</button>
</div>

<?php if (isImpersonating()): ?>
    <div style="background:#fef08a; border-bottom:2px solid var(--border-ink); padding:0.5rem 1rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; font-size:0.85rem; font-weight:700; z-index:99; position:relative;">
        <div style="display:flex; align-items:center; gap:0.4rem;">
            <span>⚠️</span>
            <span>Acting as <strong><?= e($currentUser['username']) ?></strong> (Admin Impersonation Mode)</span>
        </div>
        <div style="display:flex; gap:0.4rem; align-items:center;">
            <a href="admin" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.6rem;">⚙️ Admin Panel</a>
            <a href="admin?action=exit_impersonate" class="btn btn-danger btn-sm" style="font-size:0.75rem; padding:0.25rem 0.6rem;">↩ Exit Impersonation</a>
        </div>
    </div>
<?php endif; ?>

<main class="main-wrapper" id="main-content">
