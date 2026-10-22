<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['token'] ?? '');

if (!empty($token)) {
    $group = getGroupByToken($token);
    if ($group) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['authorized_groups'][(int)$group['id']] = $group['share_token'];
        }
        header("Location: group?token=" . urlencode($token));
        exit;
    }
}

require __DIR__ . '/404.php';
exit;
