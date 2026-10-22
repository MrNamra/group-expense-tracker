<?php
/**
 * PHP Built-in Web Server Router with Clean URL & 404 handling
 * Run with: php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// PWA Headers & File Routing
if ($uri === '/manifest.webmanifest' && file_exists(__DIR__ . '/manifest.webmanifest')) {
    header('Content-Type: application/manifest+json; charset=utf-8');
    readfile(__DIR__ . '/manifest.webmanifest');
    exit;
}
if ($uri === '/sw.js' && file_exists(__DIR__ . '/sw.js')) {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    readfile(__DIR__ . '/sw.js');
    exit;
}

// Serve static assets directly (CSS, JS, images, fonts, etc.)
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// Map root / or /dashboard to index.php
if ($uri === '/' || $uri === '/index' || $uri === '/dashboard') {
    require __DIR__ . '/index.php';
    exit;
}

// Strip leading slash
$route = ltrim($uri, '/');

// Check if route matches existing .php file
$phpFile = __DIR__ . '/' . $route . '.php';
if (file_exists($phpFile)) {
    require $phpFile;
    exit;
}

// Check if hyphenated route maps to underscored filename
$routeUnderscore = str_replace('-', '_', $route);
$phpFileUnderscore = __DIR__ . '/' . $routeUnderscore . '.php';
if (file_exists($phpFileUnderscore)) {
    require $phpFileUnderscore;
    exit;
}

// 404 Page Fallback for invalid URLs
require __DIR__ . '/404.php';
exit;
