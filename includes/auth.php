<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

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
    $ip = getSecureClientIP();

    // Rate limit registration: max 5 per hour per IP
    $regLimit = checkRateLimit('user_register', 5, 3600, $ip);
    if (!$regLimit['allowed']) {
        $mins = ceil($regLimit['retry_after'] / 60);
        return ['success' => false, 'message' => "Too many registrations from your IP. Please try again in {$mins} minute(s)."];
    }
    
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
    return getSecureClientIP();
}

// Check failed login attempts (Lockout if max attempts reached)
function isLoginLockedOut(string $input, string $ip): array {
    return checkLoginAttemptLockout($input, $ip);
}

// Record failed login attempt
function recordFailedLoginAttempt(string $input, string $ip): void {
    $db = getDBConnection();
    try {
        $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username_input) VALUES (:ip, :input)");
        $stmt->execute([':ip' => $ip, ':input' => strtolower($input)]);
    } catch (Exception $e) {}
}

// Reset failed login attempts on successful login
function resetFailedLoginAttempts(string $input, string $ip): void {
    clearLoginLockout($input, $ip);
}

// Login user safely with brute-force rate-limiting protection
function loginUser(string $usernameOrEmail, string $password): array {
    $db = getDBConnection();
    $input = trim($usernameOrEmail);
    $ip = getSecureClientIP();

    if (empty($input) || empty($password)) {
        return ['success' => false, 'message' => 'Please enter username/email and password.'];
    }

    // Check account / IP brute-force lockout
    $lockoutStatus = checkLoginAttemptLockout($input, $ip);
    if ($lockoutStatus['locked']) {
        return ['success' => false, 'message' => $lockoutStatus['message']];
    }

    // Prepared query against SQL injection
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :input OR email = :input LIMIT 1");
    $stmt->execute([':input' => $input]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Successful login: clear all failure counts
        clearLoginLockout($input, $ip);
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        return ['success' => true, 'user' => $user];
    }

    // Record failed attempt in legacy table + rate_limits
    recordFailedLoginAttempt($input, $ip);
    checkRateLimit('login_ip', defined('RATE_LIMIT_LOGIN_MAX') ? RATE_LIMIT_LOGIN_MAX : 5, defined('RATE_LIMIT_LOGIN_LOCKOUT_SEC') ? RATE_LIMIT_LOGIN_LOCKOUT_SEC : 900, $ip);
    checkRateLimit('login_user', defined('RATE_LIMIT_LOGIN_MAX') ? RATE_LIMIT_LOGIN_MAX : 5, defined('RATE_LIMIT_LOGIN_LOCKOUT_SEC') ? RATE_LIMIT_LOGIN_LOCKOUT_SEC : 900, 'usr_' . md5(strtolower($input)));

    // Re-check lockout to give accurate message
    $recheck = checkLoginAttemptLockout($input, $ip);
    if ($recheck['locked']) {
        return ['success' => false, 'message' => $recheck['message']];
    }

    $rem = $recheck['remaining_attempts'];
    return [
        'success' => false,
        'message' => "Invalid username/email or password. ({$rem} attempt(s) remaining before security lockout)"
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

// ─── ADMIN & IMPERSONATION SYSTEM ─────────────────────────────────────────

// Check if a user is an administrator
function isAdmin(?int $userId = null): bool {
    if ($userId === null) {
        if (!isLoggedIn()) return false;
        // If currently impersonating, check if the actual admin user who initiated it was admin
        if (!empty($_SESSION['admin_impersonator_id'])) {
            return true;
        }
        $userId = (int)$_SESSION['user_id'];
    }

    $db = getDBConnection();
    try {
        $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $val = $stmt->fetchColumn();
        return (int)$val === 1;
    } catch (Exception $e) {
        return false;
    }
}

// Guard: Require admin privileges or redirect
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: dashboard?msg=" . urlencode("Access denied. Administrator privileges required."));
        exit;
    }
}

// Check if currently acting as another user via admin impersonation
function isImpersonating(): bool {
    return !empty($_SESSION['admin_impersonator_id']);
}

// Get the original admin details if currently impersonating
function getImpersonatingAdmin(): ?array {
    if (!isImpersonating()) return null;
    return [
        'id'       => $_SESSION['admin_impersonator_id'],
        'username' => $_SESSION['admin_impersonator_username'] ?? 'Administrator'
    ];
}

// Impersonate any user without their password
function impersonateUser(int $targetUserId): array {
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Unauthorized. Admin access required.'];
    }

    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $targetUserId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        return ['success' => false, 'message' => 'Target user not found.'];
    }

    // Keep track of the original admin ID if not already impersonating
    if (empty($_SESSION['admin_impersonator_id'])) {
        $_SESSION['admin_impersonator_id']       = $_SESSION['user_id'];
        $_SESSION['admin_impersonator_username'] = $_SESSION['username'];
    }

    // Switch session to target user
    $_SESSION['user_id']  = $targetUser['id'];
    $_SESSION['username'] = $targetUser['username'];
    $_SESSION['email']    = $targetUser['email'];

    return ['success' => true, 'target_user' => $targetUser];
}

// Exit impersonation mode and return to original admin session
function exitImpersonation(): bool {
    if (empty($_SESSION['admin_impersonator_id'])) {
        return false;
    }

    $adminId = (int)$_SESSION['admin_impersonator_id'];
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $adminId]);
    $adminUser = $stmt->fetch();

    if ($adminUser) {
        $_SESSION['user_id']  = $adminUser['id'];
        $_SESSION['username'] = $adminUser['username'];
        $_SESSION['email']    = $adminUser['email'];
    }

    unset($_SESSION['admin_impersonator_id'], $_SESSION['admin_impersonator_username']);
    return true;
}

