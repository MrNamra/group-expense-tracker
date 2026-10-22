<?php
/**
 * PHP Built-in Web Server Router with Clean URL & 404 handling
 * Run with: php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// Block direct access to sensitive cache directory
if (strpos($uri, '/cache') === 0 || strpos($uri, '/database') === 0) {
    http_response_code(403);
    echo "403 Forbidden";
    exit;
}

// PWA Headers & File Routing
$cleanName = basename($uri);
if (($cleanName === 'manifest.webmanifest' || $cleanName === 'manifest.json') && (file_exists(__DIR__ . '/manifest.webmanifest') || file_exists(__DIR__ . '/manifest.json'))) {
    $manifestFile = file_exists(__DIR__ . '/manifest.webmanifest') ? __DIR__ . '/manifest.webmanifest' : __DIR__ . '/manifest.json';
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: true');
    header('Cache-Control: public, max-age=3600');
    readfile($manifestFile);
    exit;
}
if ($cleanName === 'sw.js' && file_exists(__DIR__ . '/sw.js')) {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile(__DIR__ . '/sw.js');
    exit;
}

// Serve static assets directly (CSS, JS, images, fonts, etc.)
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    if (php_sapi_name() === 'cli-server' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
        return false;
    }
    $mimes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'webmanifest' => 'application/manifest+json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2'
    ];
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = $mimes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($filePath);
    exit;
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
