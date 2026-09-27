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
            );"
        ];
    } else { // MySQL
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
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

    return $log;
}

