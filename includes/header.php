<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$csrfToken = generateCSRFToken();

$title = isset($pageTitle) ? $pageTitle . " | " . APP_NAME : APP_NAME . " - Group Expense Tracker & Bill Splitter";
$metaDesc = isset($pageDescription) ? $pageDescription : "Easily track group expenses, split bills with friends, manage settlements, and share expense summary links with SplitWise PRO.";
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary Meta Tags -->
    <title><?= e($title) ?></title>
    <meta name="title" content="<?= e($title) ?>">
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="keywords" content="expense tracker, bill splitter, group expenses, split bills, money manager, shared expenses, settling up">
    <meta name="author" content="SplitWise PRO">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#8b5cf6">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= e($currentUrl) ?>">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($currentUrl) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:site_name" content="SplitWise PRO">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= e($currentUrl) ?>">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($metaDesc) ?>">

    <!-- Preconnect & Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Kalam:wght@400;700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>💸</text></svg>">

    <!-- JSON-LD Structured Data Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "SplitWise PRO",
      "applicationCategory": "FinanceApplication",
      "operatingSystem": "All",
      "description": "Group expense tracking web application for splitting bills, calculating settlements, and sharing expense stats.",
      "offers": {
        "@type": "Offer",
        "price": "0.00",
        "priceCurrency": "USD"
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
