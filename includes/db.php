<?php
require_once __DIR__ . '/../config.php';

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            $dir = dirname(DB_SQLITE_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            $isNew = !file_exists(DB_SQLITE_PATH);
            $pdo = new PDO("sqlite:" . DB_SQLITE_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");

            // Auto-initialize tables if needed
            initDatabaseSchema($pdo, 'sqlite');

        } else if (DB_DRIVER === 'mysql') {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            initDatabaseSchema($pdo, 'mysql');
        } else {
            throw new Exception("Unsupported database driver: " . DB_DRIVER);
        }

        return $pdo;
    } catch (PDOException $e) {
        die("Database Connection Error: " . $e->getMessage());
    }
}

function initDatabaseSchema(PDO $pdo, string $driver) {
    if ($driver === 'sqlite') {
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );",

            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                username_input VARCHAR(100) NOT NULL,
                attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP
            );",

            "CREATE TABLE IF NOT EXISTS groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                share_token VARCHAR(64) NOT NULL UNIQUE,
                currency VARCHAR(10) DEFAULT '₹',
                upi_id VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                user_id INTEGER DEFAULT NULL,
                upi_id VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );",

            "CREATE TABLE IF NOT EXISTS expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER NOT NULL,
                title VARCHAR(150) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payer_id INTEGER NOT NULL,
                created_by INTEGER NOT NULL,
                expense_date DATE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                FOREIGN KEY (payer_id) REFERENCES participants(id) ON DELETE RESTRICT,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS expense_splits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                expense_id INTEGER NOT NULL,
                participant_id INTEGER NOT NULL,
                split_amount DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
                FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS paid_settlements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER NOT NULL,
                from_name VARCHAR(100) NOT NULL,
                to_name VARCHAR(100) NOT NULL,
                paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS user_contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                upi_id VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, name)
            );",

            "CREATE TABLE IF NOT EXISTS payment_apps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL,
                app_code VARCHAR(50) NOT NULL UNIQUE,
                uri_prefix VARCHAR(255) NOT NULL,
                icon_type VARCHAR(20) DEFAULT 'svg',
                icon_data TEXT,
                bg_color VARCHAR(30) DEFAULT '#ffffff',
                text_color VARCHAR(30) DEFAULT '#000000',
                sort_order INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );"
        ];
    } else { // MySQL
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                is_admin TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username_input VARCHAR(100) NOT NULL,
                attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `groups` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                share_token VARCHAR(64) NOT NULL UNIQUE,
                currency VARCHAR(10) DEFAULT '₹',
                upi_id VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                user_id INT DEFAULT NULL,
                upi_id VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS expenses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                title VARCHAR(150) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payer_id INT NOT NULL,
                created_by INT NOT NULL,
                expense_date DATE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                FOREIGN KEY (payer_id) REFERENCES participants(id) ON DELETE RESTRICT,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS expense_splits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                expense_id INT NOT NULL,
                participant_id INT NOT NULL,
                split_amount DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
                FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS paid_settlements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                from_name VARCHAR(100) NOT NULL,
                to_name VARCHAR(100) NOT NULL,
                paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS user_contacts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                upi_id VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_contact (user_id, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS payment_apps (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                app_code VARCHAR(50) NOT NULL UNIQUE,
                uri_prefix VARCHAR(255) NOT NULL,
                icon_type VARCHAR(20) DEFAULT 'svg',
                icon_data TEXT,
                bg_color VARCHAR(30) DEFAULT '#ffffff',
                text_color VARCHAR(30) DEFAULT '#000000',
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        ];
    }

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }

    // Automatically check and apply column & table migrations
    checkAndApplyMigrations($pdo, $driver);
}

/**
 * Check and apply incremental database migrations (SQLite & MySQL compatible)
 * Adds missing columns, missing tables, and syncs contacts without data loss.
 */
function checkAndApplyMigrations(PDO $pdo, string $driver): array {
    $log = [];

    // Helper: check if table exists
    $hasTable = function(string $table) use ($pdo, $driver): bool {
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :t");
                $stmt->execute([':t' => $table]);
                return (bool)$stmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
                ");
                $stmt->execute([':t' => $table]);
                return (int)$stmt->fetchColumn() > 0;
            }
        } catch (Exception $e) {
            return false;
        }
    };

    // Helper: check if column exists
    $hasColumn = function(string $table, string $column) use ($pdo, $driver): bool {
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info(`$table`)");
                while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (strcasecmp($col['name'], $column) === 0) return true;
                }
                return false;
            } else {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column
                ");
                $stmt->execute([':table' => $table, ':column' => $column]);
                return (int)$stmt->fetchColumn() > 0;
            }
        } catch (Exception $e) {
            return false;
        }
    };

    // ── 1. Migration: groups.upi_id column ──
    if ($hasTable('groups') && !$hasColumn('groups', 'upi_id')) {
        try {
            $pdo->exec("ALTER TABLE `groups` ADD COLUMN upi_id VARCHAR(100) DEFAULT NULL");
            $log[] = "Added column 'upi_id' to 'groups' table.";
        } catch (Exception $e) {
            $log[] = "Error adding upi_id to groups: " . $e->getMessage();
        }
    } else {
        $log[] = "Column 'groups.upi_id' verified.";
    }

    // ── 2. Migration: participants.upi_id column ──
    if ($hasTable('participants') && !$hasColumn('participants', 'upi_id')) {
        try {
            $pdo->exec("ALTER TABLE `participants` ADD COLUMN upi_id VARCHAR(100) DEFAULT NULL");
            $log[] = "Added column 'upi_id' to 'participants' table.";
        } catch (Exception $e) {
            $log[] = "Error adding upi_id to participants: " . $e->getMessage();
        }
    } else {
        $log[] = "Column 'participants.upi_id' verified.";
    }

    // ── 3. Migration: paid_settlements table ──
    if (!$hasTable('paid_settlements')) {
        try {
            if ($driver === 'sqlite') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS paid_settlements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    group_id INTEGER NOT NULL,
                    from_name VARCHAR(100) NOT NULL,
                    to_name VARCHAR(100) NOT NULL,
                    paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
                );");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS paid_settlements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    from_name VARCHAR(100) NOT NULL,
                    to_name VARCHAR(100) NOT NULL,
                    paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            $log[] = "Created table 'paid_settlements'.";
        } catch (Exception $e) {
            $log[] = "Error creating table paid_settlements: " . $e->getMessage();
        }
    } else {
        $log[] = "Table 'paid_settlements' verified.";
    }

    // ── 4. Migration: user_contacts table ──
    if (!$hasTable('user_contacts')) {
        try {
            if ($driver === 'sqlite') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_contacts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    upi_id VARCHAR(100) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE(user_id, name)
                );");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_contacts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    upi_id VARCHAR(100) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_user_contact (user_id, name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            $log[] = "Created table 'user_contacts'.";
        } catch (Exception $e) {
            $log[] = "Error creating table user_contacts: " . $e->getMessage();
        }
    } else {
        $log[] = "Table 'user_contacts' verified.";
    }

    // ── 5. Back-populate user_contacts from existing participants ──
    try {
        if ($hasTable('participants') && $hasTable('groups') && $hasTable('user_contacts') && $hasTable('users')) {
            $stmt = $pdo->query("
                SELECT p.name, p.upi_id, g.owner_id, u.username as owner_name
                FROM participants p
                JOIN `groups` g ON p.group_id = g.id
                JOIN users u ON g.owner_id = u.id
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $migratedContacts = 0;

            foreach ($rows as $r) {
                $pName = trim($r['name'] ?? '');
                $oName = trim($r['owner_name'] ?? '');
                $pUpi  = trim($r['upi_id'] ?? '');
                $oId   = (int)$r['owner_id'];

                if (!empty($pName) && strcasecmp($pName, $oName) !== 0) {
                    // Check if contact already exists
                    $cCheck = $pdo->prepare("SELECT id, upi_id FROM user_contacts WHERE user_id = :uid AND LOWER(name) = LOWER(:name)");
                    $cCheck->execute([':uid' => $oId, ':name' => $pName]);
                    $existing = $cCheck->fetch(PDO::FETCH_ASSOC);

                    if (!$existing) {
                        $cIns = $pdo->prepare("INSERT INTO user_contacts (user_id, name, upi_id) VALUES (:uid, :name, :upi)");
                        $cIns->execute([':uid' => $oId, ':name' => $pName, ':upi' => $pUpi ?: null]);
                        $migratedContacts++;
                    } elseif (!empty($pUpi) && empty($existing['upi_id'])) {
                        $cUpd = $pdo->prepare("UPDATE user_contacts SET upi_id = :upi WHERE id = :id");
                        $cUpd->execute([':upi' => $pUpi, ':id' => $existing['id']]);
                        $migratedContacts++;
                    }
                }
            }

            if ($migratedContacts > 0) {
                $log[] = "Synced {$migratedContacts} existing participant(s) into universal contacts.";
            } else {
                $log[] = "Universal contacts are fully synced with existing participants.";
            }
        }
    } catch (Exception $e) {
        $log[] = "Contacts sync check: " . $e->getMessage();
    }

    // ── 6. Migration: users.is_admin column ──
    if ($hasTable('users') && !$hasColumn('users', 'is_admin')) {
        try {
            if ($driver === 'sqlite') {
                $pdo->exec("ALTER TABLE users ADD COLUMN is_admin INTEGER DEFAULT 0");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
            }
            $log[] = "Added column 'is_admin' to 'users' table.";
        } catch (Exception $e) {
            $log[] = "Error adding is_admin to users: " . $e->getMessage();
        }
    } else {
        $log[] = "Column 'users.is_admin' verified.";
    }

    // Auto-promote initial administrator(s) if none exists
    try {
        if ($hasTable('users') && $hasColumn('users', 'is_admin')) {
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
            if ($adminCount === 0) {
                $pdo->exec("UPDATE users SET is_admin = 1 WHERE id = 1 OR email = 'test@test.com'");
                $log[] = "Promoted initial administrator account(s).";
            }
        }
    } catch (Exception $e) {
        $log[] = "Admin initial setup check: " . $e->getMessage();
    }

    // ── 7. Migration: payment_apps table & defaults ──
    if (!$hasTable('payment_apps')) {
        try {
            if ($driver === 'sqlite') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS payment_apps (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(50) NOT NULL,
                    app_code VARCHAR(50) NOT NULL UNIQUE,
                    uri_prefix VARCHAR(255) NOT NULL,
                    icon_type VARCHAR(20) DEFAULT 'svg',
                    icon_data TEXT,
                    bg_color VARCHAR(30) DEFAULT '#ffffff',
                    text_color VARCHAR(30) DEFAULT '#000000',
                    sort_order INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS payment_apps (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(50) NOT NULL,
                    app_code VARCHAR(50) NOT NULL UNIQUE,
                    uri_prefix VARCHAR(255) NOT NULL,
                    icon_type VARCHAR(20) DEFAULT 'svg',
                    icon_data TEXT,
                    bg_color VARCHAR(30) DEFAULT '#ffffff',
                    text_color VARCHAR(30) DEFAULT '#000000',
                    sort_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            $log[] = "Created table 'payment_apps'.";
        } catch (Exception $e) {
            $log[] = "Error creating table payment_apps: " . $e->getMessage();
        }
    } else {
        $log[] = "Table 'payment_apps' verified.";
    }

    // Seed default payment apps if table is empty
    try {
        if ($hasTable('payment_apps')) {
            $appCount = (int)$pdo->query("SELECT COUNT(*) FROM payment_apps")->fetchColumn();
            if ($appCount === 0) {
                $stmt = $pdo->prepare("INSERT INTO payment_apps (name, app_code, uri_prefix, icon_type, icon_data, bg_color, text_color, sort_order, is_active) VALUES (:name, :code, :uri, :itype, :idata, :bg, :text, :sort, 1)");
                
                $defaults = [
                    [
                        'name' => 'GPay',
                        'code' => 'gpay',
                        'uri' => 'gpay://upi/pay?',
                        'itype' => 'svg',
                        'idata' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/></svg>',
                        'bg' => '#ffffff',
                        'text' => '#1f2937',
                        'sort' => 1
                    ],
                    [
                        'name' => 'PhonePe',
                        'code' => 'phonepe',
                        'uri' => 'phonepe://pay?',
                        'itype' => 'svg',
                        'idata' => '<svg width="18" height="18" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" fill="#5F259F"/><path d="M19.5 8H12a1 1 0 0 0-1 1v2.5h3.2v-1.5h4.8c.8 0 1.5.7 1.5 1.5v1.2c0 .8-.7 1.5-1.5 1.5h-5.2a1 1 0 0 0-1 1v7.8a1 1 0 0 0 2 0v-4.8h3.2l3.4 5.2a1 1 0 0 0 1.7-1.1L21.4 17c1.7-.6 2.8-2.2 2.8-4v-1c0-2.2-1.8-4-4-4z" fill="#FFFFFF"/></svg>',
                        'bg' => '#5f259f',
                        'text' => '#ffffff',
                        'sort' => 2
                    ],
                    [
                        'name' => 'Paytm',
                        'code' => 'paytm',
                        'uri' => 'paytmmp://pay?',
                        'itype' => 'svg',
                        'idata' => '<svg width="26" height="14" viewBox="0 0 46 16" fill="none"><text x="0" y="13" font-size="13" font-weight="900" fill="#ffffff" font-family="sans-serif">Pay</text><text x="24" y="13" font-size="13" font-weight="900" fill="#00B9F5" font-family="sans-serif">tm</text></svg>',
                        'bg' => '#002e6e',
                        'text' => '#00b9f5',
                        'sort' => 3
                    ],
                    [
                        'name' => 'BHIM',
                        'code' => 'bhim',
                        'uri' => 'bhim://pay?',
                        'itype' => 'svg',
                        'idata' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="4" fill="#005792"/><path d="M6 18L14 6h4L10 18H6z" fill="#00A859"/><path d="M12 18L18 9h2l-6 9h-2z" fill="#FF8300"/></svg>',
                        'bg' => '#005792',
                        'text' => '#ffffff',
                        'sort' => 4
                    ],
                    [
                        'name' => 'Other UPI App',
                        'code' => 'generic',
                        'uri' => 'upi://pay?',
                        'itype' => 'svg',
                        'idata' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
                        'bg' => '#1e293b',
                        'text' => '#ffffff',
                        'sort' => 5
                    ]
                ];

                foreach ($defaults as $d) {
                    $stmt->execute([
                        ':name'  => $d['name'],
                        ':code'  => $d['code'],
                        ':uri'   => $d['uri'],
                        ':itype' => $d['itype'],
                        ':idata' => $d['idata'],
                        ':bg'    => $d['bg'],
                        ':text'  => $d['text'],
                        ':sort'  => $d['sort']
                    ]);
                }
                $log[] = "Seeded 5 default payment apps (GPay, PhonePe, Paytm, BHIM, Generic UPI).";
            }
        }
    } catch (Exception $e) {
        $log[] = "Payment apps seeding: " . $e->getMessage();
    }

    // Clean up activity_logs table if previously created
    if ($hasTable('activity_logs')) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS activity_logs");
            $log[] = "Removed deprecated 'activity_logs' table (activity is queried directly from core tables for zero write overhead).";
        } catch (Exception $e) {}
    }

    return $log;
}

