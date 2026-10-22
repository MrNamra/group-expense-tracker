<?php
/**
 * Application Configuration File
 *
 * Switch database drivers, host credentials, or app settings easily here.
 */

// Database Settings
define('DB_DRIVER', 'sqlite'); // Options: 'sqlite' or 'mysql'

// MySQL Configuration (Used if DB_DRIVER is 'mysql')
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'expense_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');

// SQLite Configuration (Used if DB_DRIVER is 'sqlite')
define('DB_SQLITE_PATH', __DIR__ . '/database/expense_tracker.sqlite');

// Application General Settings
define('APP_NAME', 'SplitWise PRO - Group Expense Tracker');
define('APP_URL', 'https://expenses.freedev.app');
define('APP_CURRENCY_SYMBOL', '₹');

// Supported Currencies List
define('SUPPORTED_CURRENCIES', [
    '₹' => 'INR (₹)',
    '$' => 'USD ($)',
    '€' => 'EUR (€)',
    '£' => 'GBP (£)',
    '¥' => 'JPY (¥)',
    'A$' => 'AUD (A$)',
    'C$' => 'CAD (C$)',
    'AED' => 'AED (Dh)',
    '৳' => 'BDT (৳)',
    'Rs' => 'PKR / NPR (Rs)'
]);

// Session & Security Settings
if (session_status() === PHP_SESSION_NONE) {
    // Ensure session save path is writable across different environments
    $sessPath = session_save_path();
    if (empty($sessPath) || !@is_writable($sessPath)) {
        @ini_set('session.save_path', sys_get_temp_dir());
    }
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Rate Limiting & Security Configuration
define('ENABLE_RATE_LIMITING', true);
define('RATE_LIMIT_GLOBAL_MAX', 150);      // Max requests per minute per IP
define('RATE_LIMIT_LOGIN_MAX', 5);         // Max failed login attempts before lockout
define('RATE_LIMIT_LOGIN_LOCKOUT_SEC', 900); // 15 minutes lockout duration
define('RATE_LIMIT_PASSWORD_RESET_MAX', 3); // Max reset requests per 15 minutes

