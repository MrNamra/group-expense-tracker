<?php
require_once __DIR__ . '/db.php';

// Helper for XSS escaping
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Generate CSRF token
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken(?string $token): bool {
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Check if user is logged in
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get logged in user details
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email']
    ];
}

// Require authentication for protected routes
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login?msg=" . urlencode("Please log in to access this page."));
        exit;
    }
}

// Register user safely
function registerUser(string $username, string $email, string $password): array {
    $db = getDBConnection();
    
    $username = trim($username);
    $email = strtolower(trim($email));

    if (empty($username) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'All fields are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address format.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }

    // Check if username or email already exists (Prepared Query)
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $username, ':email' => $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already registered.'];
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
    
    try {
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashedPassword
        ]);
        
        // Note: Do NOT auto-login user upon registration; user must log in manually.
        return ['success' => true, 'message' => 'Registration successful! Please log in to your account.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage()];
    }
}

// Get client IP address
function getClientIP(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// Check failed login attempts (Lockout if 5 failed attempts in 1 hour)
function isLoginLockedOut(string $input, string $ip): array {
    $db = getDBConnection();
    $maxAttempts = 5;

    if (DB_DRIVER === 'sqlite') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE (username_input = :input OR ip_address = :ip) 
              AND attempt_time >= datetime('now', '-1 hour')
        ");
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE (username_input = :input OR ip_address = :ip) 
              AND attempt_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
    }

    $stmt->execute([':input' => strtolower($input), ':ip' => $ip]);
    $row = $stmt->fetch();
    $attempts = (int) ($row['attempts'] ?? 0);

    if ($attempts >= $maxAttempts) {
        return [
            'locked' => true,
            'message' => 'Too many failed login attempts (5/5). Account temporarily locked for 1 hour for security.'
        ];
    }

    return ['locked' => false, 'attempts' => $attempts];
}

// Record failed login attempt
function recordFailedLoginAttempt(string $input, string $ip): void {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username_input) VALUES (:ip, :input)");
    $stmt->execute([':ip' => $ip, ':input' => strtolower($input)]);
}

// Reset failed login attempts on successful login
function resetFailedLoginAttempts(string $input, string $ip): void {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE username_input = :input OR ip_address = :ip");
    $stmt->execute([':input' => strtolower($input), ':ip' => $ip]);
}

// Login user safely with brute-force protection
function loginUser(string $usernameOrEmail, string $password): array {
    $db = getDBConnection();
    $input = trim($usernameOrEmail);
    $ip = getClientIP();

    if (empty($input) || empty($password)) {
        return ['success' => false, 'message' => 'Please enter username/email and password.'];
    }

    // Check account/IP lockout
    $lockoutStatus = isLoginLockedOut($input, $ip);
    if ($lockoutStatus['locked']) {
        return ['success' => false, 'message' => $lockoutStatus['message']];
    }

    // Prepared query against SQL injection
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :input OR email = :input LIMIT 1");
    $stmt->execute([':input' => $input]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        resetFailedLoginAttempts($input, $ip);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        return ['success' => true, 'user' => $user];
    }

    // Record failed attempt
    recordFailedLoginAttempt($input, $ip);
    $currentAttempts = $lockoutStatus['attempts'] + 1;
    $remaining = 5 - $currentAttempts;

    if ($remaining <= 0) {
        return ['success' => false, 'message' => 'Too many failed attempts. Account has been locked for 1 hour for security.'];
    }

    return [
        'success' => false,
        'message' => "Invalid username/email or password. ($remaining attempts remaining before lockout)"
    ];
}

// Generate unique 6-8 character short token (a-z, 0-9) for group sharing
function generateGroupShareToken(int $length = 6): string {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($chars) - 1;
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, $max)];
    }
    return $token;
}

// Check if current user is owner of group
function isGroupOwner(int $groupId, ?int $userId = null): bool {
    if ($userId === null) {
        if (!isLoggedIn()) return false;
        $userId = $_SESSION['user_id'];
    }

    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id FROM groups WHERE id = :group_id AND owner_id = :owner_id");
    $stmt->execute([':group_id' => $groupId, ':owner_id' => $userId]);
    return (bool) $stmt->fetch();
}
