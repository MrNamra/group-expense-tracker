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

$groupId       = (int) ($_POST['group_id'] ?? 0);
$participantId = (int) ($_POST['participant_id'] ?? 0);
$upiId         = trim($_POST['upi_id'] ?? '');

$currentUser = getCurrentUser();

if (!isGroupOwner($groupId, $currentUser['id'])) {
    header("Location: group?id={$groupId}&error=" . urlencode("Only the group owner can set participant UPI IDs."));
    exit;
}

if (!$groupId || !$participantId) {
    header("Location: group?id={$groupId}&error=" . urlencode("Invalid request."));
    exit;
}

$db = getDBConnection();

// Confirm participant belongs to this group, get their name
$stmt = $db->prepare("SELECT id, name FROM participants WHERE id = :pid AND group_id = :gid");
$stmt->execute([':pid' => $participantId, ':gid' => $groupId]);
$participant = $stmt->fetch();

if (!$participant) {
    header("Location: group?id={$groupId}&error=" . urlencode("Participant not found in this group."));
    exit;
}

// Update participant UPI
$stmt = $db->prepare("UPDATE participants SET upi_id = :upi WHERE id = :pid AND group_id = :gid");
$stmt->execute([':upi' => $upiId ?: null, ':pid' => $participantId, ':gid' => $groupId]);

// Sync to owner's universal contact book
updateContactUpi($currentUser['id'], $participant['name'], $upiId);

header("Location: group?id={$groupId}&msg=" . urlencode("UPI ID updated for {$participant['name']}."));
exit;
