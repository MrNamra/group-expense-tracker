<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$groupId = (int) ($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!verifyCSRFToken($token)) {
    header("Location: group?id=" . $groupId . "&error=" . urlencode("Invalid security token."));
    exit;
}

if (!isGroupOwner($groupId, $currentUser['id'])) {
    header("Location: group?id=" . $groupId . "&error=" . urlencode("Only owner can delete group."));
    exit;
}

$db = getDBConnection();
$stmt = $db->prepare("DELETE FROM groups WHERE id = :id AND owner_id = :owner_id");
$stmt->execute([':id' => $groupId, ':owner_id' => $currentUser['id']]);

header("Location: dashboard?msg=" . urlencode("Group deleted successfully."));
exit;
