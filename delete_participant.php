<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$participantId = (int) ($_GET['id'] ?? 0);
$groupId = (int) ($_GET['group_id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!verifyCSRFToken($token)) {
    header("Location: group?id=" . $groupId . "&error=" . urlencode("Invalid security token."));
    exit;
}

if (!isGroupOwner($groupId, $currentUser['id'])) {
    header("Location: group?id=" . $groupId . "&error=" . urlencode("Only the group owner can delete participants."));
    exit;
}

$res = deleteParticipant($groupId, $participantId);
if ($res['success']) {
    header("Location: group?id=" . $groupId . "&msg=" . urlencode($res['message']));
} else {
    header("Location: group?id=" . $groupId . "&error=" . urlencode($res['message']));
}
exit;
