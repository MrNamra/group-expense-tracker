<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>💸</text></svg>">
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="dashboard" class="brand-logo">
            <div class="brand-icon">💸</div>
            <span>SplitWise <strong style="font-weight: 400; font-size: 0.85em; opacity: 0.8;">PRO</strong></span>
        </a>

        <div class="nav-links">
            <?php if (isLoggedIn()): ?>
                <div class="user-badge">
                    <span>👤</span>
                    <span>Logged in as <strong><?= e($currentUser['username']) ?></strong></span>
                </div>
                <a href="dashboard" class="btn btn-secondary btn-sm">Dashboard</a>
                <a href="logout" class="btn btn-danger btn-sm">Logout</a>
            <?php else: ?>
                <a href="login" class="btn btn-secondary btn-sm">Login</a>
                <a href="register" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="main-wrapper">
