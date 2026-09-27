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
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
