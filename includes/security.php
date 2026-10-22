<?php
/**
 * SplitWise PRO - Security & Rate Limiting Engine
 *
 * Implements:
 * - Dynamic HTTP Rate Limiting (Sliding Window per IP & Action)
 * - Brute-force Login Protection with automatic lockouts
 * - Safe Self-Service Password Reset Token Lifecycle
 * - Production Security Headers & Injection Safeguards
 * - Administrative Rate Limit inspection and unblocking
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// ── Security Headers ──────────────────────────────────────────────────────────
function applySecurityHeaders(): void {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    
    // Content-Security-Policy (Permissive for Google Fonts, SVGs, QR codes, PWA)
    $csp = "default-src 'self' 'unsafe-inline' https: data:; " .
           "img-src 'self' data: https: blob:; " .
           "script-src 'self' 'unsafe-inline' https:; " .
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
           "font-src 'self' https://fonts.gstatic.com data:; " .
           "connect-src 'self' https:;";
    header("Content-Security-Policy: {$csp}");
}

// ── Client IP Detection ───────────────────────────────────────────────────────
function getSecureClientIP(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_REAL_IP',        // Nginx proxy
        'HTTP_X_FORWARDED_FOR',  // Standard proxy
        'REMOTE_ADDR'            // Direct connection
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ipList = explode(',', $_SERVER[$header]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// ── Rate Limiter Engine (Sliding Window) ───────────────────────────────────────
/**
 * Check and record a rate limited action.
 *
 * @param string $actionKey Unique action identifier (e.g. 'global', 'login', 'reset_pwd')
 * @param int $maxHits Maximum requests permitted in window
 * @param int $windowSeconds Duration of window in seconds
 * @param string|null $identifier Optional custom identifier (defaults to client IP)
 * @return array ['allowed' => bool, 'current' => int, 'max' => int, 'retry_after' => int, 'remaining' => int]
 */
function checkRateLimit(string $actionKey, int $maxHits, int $windowSeconds, ?string $identifier = null): array {
    if (!defined('ENABLE_RATE_LIMITING') || !ENABLE_RATE_LIMITING) {
        return ['allowed' => true, 'current' => 1, 'max' => $maxHits, 'retry_after' => 0, 'remaining' => $maxHits];
    }

    $identifier = $identifier ?: getSecureClientIP();
    $now = time();
    $db = getDBConnection();

    try {
        // Look up active rate limit row
        $stmt = $db->prepare("
            SELECT id, hit_count, first_hit_at, blocked_until 
            FROM rate_limits 
            WHERE identifier = :ident AND action_key = :act
            LIMIT 1
        ");
        $stmt->execute([':ident' => $identifier, ':act' => $actionKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $blockedUntil = (int)$row['blocked_until'];
            $firstHitAt   = (int)$row['first_hit_at'];
            $hitCount     = (int)$row['hit_count'];
            $rowId        = (int)$row['id'];

            // 1. Is it currently blocked?
            if ($blockedUntil > $now) {
                $retryAfter = $blockedUntil - $now;
                return [
                    'allowed'     => false,
                    'current'     => $hitCount,
                    'max'         => $maxHits,
                    'retry_after' => $retryAfter,
                    'remaining'   => 0
                ];
            }

            // 2. Has the time window expired? Reset window
            if (($now - $firstHitAt) >= $windowSeconds) {
                $upd = $db->prepare("
                    UPDATE rate_limits 
                    SET hit_count = 1, first_hit_at = :now, blocked_until = 0 
                    WHERE id = :id
                ");
                $upd->execute([':now' => $now, ':id' => $rowId]);

                return [
                    'allowed'     => true,
                    'current'     => 1,
                    'max'         => $maxHits,
                    'retry_after' => 0,
                    'remaining'   => max(0, $maxHits - 1)
                ];
            }

            // 3. Within window: increment hits
            $newCount = $hitCount + 1;
            if ($newCount > $maxHits) {
                $blockUntil = $now + $windowSeconds;
                $upd = $db->prepare("
                    UPDATE rate_limits 
                    SET hit_count = :cnt, blocked_until = :blk 
                    WHERE id = :id
                ");
                $upd->execute([':cnt' => $newCount, ':blk' => $blockUntil, ':id' => $rowId]);

                return [
                    'allowed'     => false,
                    'current'     => $newCount,
                    'max'         => $maxHits,
                    'retry_after' => $windowSeconds,
                    'remaining'   => 0
                ];
            } else {
                $upd = $db->prepare("UPDATE rate_limits SET hit_count = :cnt WHERE id = :id");
                $upd->execute([':cnt' => $newCount, ':id' => $rowId]);

                return [
                    'allowed'     => true,
                    'current'     => $newCount,
                    'max'         => $maxHits,
                    'retry_after' => 0,
                    'remaining'   => max(0, $maxHits - $newCount)
                ];
            }
        } else {
            // New record for this identifier and action
            $ins = $db->prepare("
                INSERT INTO rate_limits (identifier, action_key, hit_count, first_hit_at, blocked_until)
                VALUES (:ident, :act, 1, :now, 0)
            ");
            $ins->execute([':ident' => $identifier, ':act' => $actionKey, ':now' => $now]);

            return [
                'allowed'     => true,
                'current'     => 1,
                'max'         => $maxHits,
                'retry_after' => 0,
                'remaining'   => max(0, $maxHits - 1)
            ];
        }
    } catch (Exception $e) {
        // Fallback open if DB has an issue during high concurrency
        return ['allowed' => true, 'current' => 1, 'max' => $maxHits, 'retry_after' => 0, 'remaining' => $maxHits];
    }
}

// ── Global Site-Wide HTTP Request Rate Limiter ────────────────────────────────
function enforceGlobalRateLimit(): void {
    if (php_sapi_name() === 'cli') {
        return;
    }

    // Exclude static assets from rate limiting
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $ext = strtolower(pathinfo(parse_url($uri, PHP_URL_PATH), PATHINFO_EXTENSION));
    $staticExts = ['css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'ico', 'webmanifest', 'json', 'woff2', 'woff', 'ttf'];
    if (in_array($ext, $staticExts, true) || strpos($uri, 'sw.js') !== false) {
        return;
    }

    $maxReqs = defined('RATE_LIMIT_GLOBAL_MAX') ? RATE_LIMIT_GLOBAL_MAX : 150;
    $res = checkRateLimit('http_global', $maxReqs, 60);

    if (!$res['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . $res['retry_after']);
        header('Content-Type: text/html; charset=UTF-8');
        echo renderRateLimitPage($res['retry_after']);
        exit;
    }
}

// Clean 429 Error UI for users
function renderRateLimitPage(int $retryAfter): string {
    $appName = defined('APP_NAME') ? APP_NAME : 'SplitWise PRO';
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Limit Exceeded | {$appName}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1.5rem;
        }
        .card {
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 16px;
            box-shadow: 4px 4px 0px #0f172a;
            max-width: 480px;
            width: 100%;
            padding: 2.2rem;
            text-align: center;
        }
        .icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.6rem;
            margin: 0 0 0.5rem 0;
            font-weight: 800;
        }
        p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0 0 1.5rem 0;
        }
        .timer-box {
            background: #fef2f2;
            border: 1.5px solid #fecaca;
            color: #b91c1c;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            display: inline-block;
        }
        .btn {
            display: inline-block;
            background: #6d28d9;
            color: #ffffff;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 700;
            border: 2px solid #0f172a;
            box-shadow: 2px 2px 0px #0f172a;
            cursor: pointer;
        }
        .btn:hover {
            background: #5b21b6;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🛡️</div>
        <h1>Too Many Requests</h1>
        <p>You have made too many requests in a short period. Please wait a moment before trying again to protect platform security.</p>
        <div class="timer-box">
            ⏳ Please wait <span id="countdown">{$retryAfter}</span> seconds
        </div>
        <div>
            <button class="btn" onclick="window.location.reload()">🔄 Try Again</button>
        </div>
    </div>
    <script>
        let seconds = {$retryAfter};
        const el = document.getElementById('countdown');
        const timer = setInterval(() => {
            seconds--;
            if (seconds <= 0) {
                clearInterval(timer);
                el.innerText = '0';
                window.location.reload();
            } else {
                el.innerText = seconds;
            }
        }, 1000);
    </script>
</body>
</html>
HTML;
}

// ── Login Rate Limiting (Brute-Force Shield) ──────────────────────────────────
/**
 * Check if a login attempt is rate limited by IP or username.
 *
 * @param string $input Username or email entered
 * @param string $ip Client IP
 * @return array ['locked' => bool, 'message' => string, 'remaining_attempts' => int, 'retry_after' => int]
 */
function checkLoginAttemptLockout(string $input, string $ip): array {
    $maxAttempts = defined('RATE_LIMIT_LOGIN_MAX') ? RATE_LIMIT_LOGIN_MAX : 5;
    $lockoutSec  = defined('RATE_LIMIT_LOGIN_LOCKOUT_SEC') ? RATE_LIMIT_LOGIN_LOCKOUT_SEC : 900; // 15 mins

    // Check IP rate limit
    $ipLimit = checkRateLimit('login_ip', $maxAttempts, $lockoutSec, $ip);
    if (!$ipLimit['allowed']) {
        $minutes = ceil($ipLimit['retry_after'] / 60);
        return [
            'locked'             => true,
            'message'            => "Too many failed login attempts from your IP. Access temporarily locked for {$minutes} minute(s) for security.",
            'remaining_attempts' => 0,
            'retry_after'        => $ipLimit['retry_after']
        ];
    }

    // Check username/email rate limit
    $cleanInput = strtolower(trim($input));
    if (!empty($cleanInput)) {
        $userLimit = checkRateLimit('login_user', $maxAttempts, $lockoutSec, 'usr_' . md5($cleanInput));
        if (!$userLimit['allowed']) {
            $minutes = ceil($userLimit['retry_after'] / 60);
            return [
                'locked'             => true,
                'message'            => "This account is temporarily protected due to multiple failed login attempts. Please wait {$minutes} minute(s) or use 'Forgot Password'.",
                'remaining_attempts' => 0,
                'retry_after'        => $userLimit['retry_after']
            ];
        }
    }

    $remaining = min($ipLimit['remaining'], !empty($cleanInput) ? $userLimit['remaining'] : $ipLimit['remaining']);

    return [
        'locked'             => false,
        'message'            => '',
        'remaining_attempts' => $remaining,
        'retry_after'        => 0
    ];
}

// Clear login rate limit upon successful authentication
function clearLoginLockout(string $input, string $ip): void {
    $db = getDBConnection();
    try {
        $cleanInput = 'usr_' . md5(strtolower(trim($input)));
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE (identifier = :ip AND action_key = 'login_ip') OR (identifier = :usr AND action_key = 'login_user')");
        $stmt->execute([':ip' => $ip, ':usr' => $cleanInput]);

        // Also clean legacy login_attempts table
        $stmt2 = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip OR username_input = :inp");
        $stmt2->execute([':ip' => $ip, ':inp' => strtolower(trim($input))]);
    } catch (Exception $e) {}
}

// ── Password Reset System ─────────────────────────────────────────────────────

/**
 * Initiate a self-service password reset request.
 *
 * @param string $usernameOrEmail
 * @param string $ip
 * @return array ['success' => bool, 'message' => string, 'token' => ?string, 'reset_url' => ?string]
 */
function createPasswordResetRequest(string $usernameOrEmail, string $ip): array {
    $input = trim($usernameOrEmail);
    if (empty($input)) {
        return ['success' => false, 'message' => 'Please enter your username or registered email address.'];
    }

    // Rate limit reset requests to prevent email enumeration / flooding (max 3 per 15 minutes)
    $rateLimit = checkRateLimit('pwd_reset_ip', defined('RATE_LIMIT_PASSWORD_RESET_MAX') ? RATE_LIMIT_PASSWORD_RESET_MAX : 3, 900, $ip);
    if (!$rateLimit['allowed']) {
        $mins = ceil($rateLimit['retry_after'] / 60);
        return [
            'success' => false,
            'message' => "Too many password reset requests. Please wait {$mins} minute(s) before requesting again."
        ];
    }

    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, username, email FROM users WHERE username = :u OR email = :e LIMIT 1");
    $stmt->execute([':u' => $input, ':e' => strtolower($input)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Return a generic success-sounding message to prevent user enumeration
        return [
            'success'   => true,
            'message'   => 'If an account exists with that username or email, password reset instructions have been generated.',
            'token'     => null,
            'reset_url' => null
        ];
    }

    // Invalidate any existing unused tokens for this user
    try {
        $inv = $db->prepare("UPDATE password_resets SET used = 1 WHERE user_id = :uid AND used = 0");
        $inv->execute([':uid' => $user['id']]);
    } catch (Exception $e) {}

    // Generate secure 64-character token
    $token = bin2hex(random_bytes(32));
    
    // Expires in 1 hour
    if (DB_DRIVER === 'sqlite') {
        $stmt = $db->prepare("
            INSERT INTO password_resets (user_id, token, expires_at, used, ip_address) 
            VALUES (:uid, :token, datetime('now', '+1 hour'), 0, :ip)
        ");
    } else {
        $stmt = $db->prepare("
            INSERT INTO password_resets (user_id, token, expires_at, used, ip_address) 
            VALUES (:uid, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR), 0, :ip)
        ");
    }

    $stmt->execute([
        ':uid'   => $user['id'],
        ':token' => $token,
        ':ip'    => $ip
    ]);

    // Build reset URL
    $baseUrl = rtrim(defined('APP_URL') ? APP_URL : 'https://expenses.freedev.app', '/');
    // If running on local server, detect host
    if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $proto . '://' . $_SERVER['HTTP_HOST'];
    }
    $resetUrl = $baseUrl . '/reset_password?token=' . urlencode($token);

    return [
        'success'   => true,
        'message'   => 'Password reset link generated successfully! You can reset your password now.',
        'token'     => $token,
        'reset_url' => $resetUrl,
        'user'      => $user
    ];
}

/**
 * Validate a password reset token.
 *
 * @param string $token
 * @return array|null Returns reset record and user details or null if invalid/expired
 */
function verifyPasswordResetToken(string $token): ?array {
    $token = trim($token);
    if (empty($token) || strlen($token) !== 64) {
        return null;
    }

    $db = getDBConnection();
    try {
        if (DB_DRIVER === 'sqlite') {
            $stmt = $db->prepare("
                SELECT pr.*, u.username, u.email 
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.token = :tok 
                  AND pr.used = 0 
                  AND pr.expires_at >= datetime('now')
                LIMIT 1
            ");
        } else {
            $stmt = $db->prepare("
                SELECT pr.*, u.username, u.email 
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.token = :tok 
                  AND pr.used = 0 
                  AND pr.expires_at >= NOW()
                LIMIT 1
            ");
        }

        $stmt->execute([':tok' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Complete password reset with new password.
 *
 * @param string $token
 * @param string $newPassword
 * @return array ['success' => bool, 'message' => string]
 */
function completePasswordReset(string $token, string $newPassword): array {
    $tokenInfo = verifyPasswordResetToken($token);
    if (!$tokenInfo) {
        return ['success' => false, 'message' => 'This password reset link is invalid or has expired. Please request a new one.'];
    }

    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'New password must be at least 6 characters in length.'];
    }

    $db = getDBConnection();
    try {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        // Update user password
        $updUser = $db->prepare("UPDATE users SET password = :pwd WHERE id = :id");
        $updUser->execute([':pwd' => $hashedPassword, ':id' => $tokenInfo['user_id']]);

        // Mark token as used
        $updToken = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
        $updToken->execute([':id' => $tokenInfo['id']]);

        // Lift any login lockout for this user
        clearLoginLockout($tokenInfo['username'], getSecureClientIP());

        return ['success' => true, 'message' => 'Your password has been reset successfully! You can now log in with your new password.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error resetting password: ' . $e->getMessage()];
    }
}

// ── Admin Security Oversight Functions ────────────────────────────────────────

// Retrieve recent rate limits for admin inspection
function getAdminRateLimits(int $limit = 50): array {
    $db = getDBConnection();
    try {
        $stmt = $db->prepare("
            SELECT * FROM rate_limits 
            ORDER BY blocked_until DESC, first_hit_at DESC 
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Retrieve recent password reset requests for admin oversight
function getAdminPasswordResets(int $limit = 50): array {
    $db = getDBConnection();
    try {
        $stmt = $db->prepare("
            SELECT pr.*, u.username, u.email 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            ORDER BY pr.id DESC 
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Unlock an IP or user in rate limits
function unlockRateLimitIdentifier(string $identifier): bool {
    $db = getDBConnection();
    try {
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE identifier = :id");
        return $stmt->execute([':id' => $identifier]);
    } catch (Exception $e) {
        return false;
    }
}

// Clear all expired or old rate limit records
function clearExpiredRateLimits(): bool {
    $db = getDBConnection();
    try {
        $now = time();
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE blocked_until < :now AND first_hit_at < (:now - 3600)");
        return $stmt->execute([':now' => $now]);
    } catch (Exception $e) {
        return false;
    }
}
