<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard");
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header("Location: dashboard?error=" . urlencode("Invalid CSRF token."));
    exit;
}

$groupId   = (int) ($_POST['group_id'] ?? 0);
$fromName  = trim($_POST['from_name'] ?? '');
$toName    = trim($_POST['to_name'] ?? '');

$currentUser = getCurrentUser();

if (!isGroupOwner($groupId, $currentUser['id'])) {
    header("Location: group?id={$groupId}&error=" . urlencode("Only the group owner can mark settlements as paid."));
    exit;
}

if (empty($fromName) || empty($toName) || !$groupId) {
    header("Location: group?id={$groupId}&error=" . urlencode("Invalid settlement data."));
    exit;
}

$db = getDBConnection();

// Check if already marked paid
$stmt = $db->prepare("SELECT id FROM paid_settlements WHERE group_id = :gid AND from_name = :from AND to_name = :to");
$stmt->execute([':gid' => $groupId, ':from' => $fromName, ':to' => $toName]);
if (!$stmt->fetch()) {
    $stmt = $db->prepare("INSERT INTO paid_settlements (group_id, from_name, to_name) VALUES (:gid, :from, :to)");
    $stmt->execute([':gid' => $groupId, ':from' => $fromName, ':to' => $toName]);
}

header("Location: group?id={$groupId}&msg=" . urlencode("{$fromName} marked as paid to {$toName}."));
exit;
