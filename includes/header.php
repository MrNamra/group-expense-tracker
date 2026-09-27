<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ── Primary SEO Meta Tags ── -->
    <title><?= e($title) ?></title>
    <meta name="title"       content="<?= e($title) ?>">
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="keywords"    content="<?= e($metaKW) ?>">
    <meta name="author"      content="SplitWise PRO">
    <meta name="robots"      content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="theme-color" content="#8b5cf6">
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
                    <span>Logged in as <strong><?= e($currentUser['username']) ?></strong></span>
                </div>
                <a href="dashboard" class="btn btn-secondary btn-sm" id="nav-link-dashboard">Dashboard</a>
                <a href="logout" class="btn btn-danger btn-sm" id="nav-link-logout">Logout</a>
            <?php else: ?>
                <a href="login" class="btn btn-secondary btn-sm" id="nav-link-login">Login</a>
                <a href="register" class="btn btn-primary btn-sm" id="nav-link-register">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="main-wrapper" id="main-content">
