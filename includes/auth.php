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
        
        $userId = $db->lastInsertId();
        
        // Auto-login after registration
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;

        return ['success' => true, 'message' => 'Registration successful!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage()];
    }
}

// Login user safely
function loginUser(string $usernameOrEmail, string $password): array {
    $db = getDBConnection();
    $input = trim($usernameOrEmail);

    if (empty($input) || empty($password)) {
        return ['success' => false, 'message' => 'Please enter username/email and password.'];
    }

    // Prepared query against SQL injection
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :input OR email = :input LIMIT 1");
    $stmt->execute([':input' => $input]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        return ['success' => true, 'user' => $user];
    }

    return ['success' => false, 'message' => 'Invalid username/email or password.'];
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
