<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupId = (int) ($_POST['group_id'] ?? 0);
    $token   = $_POST['csrf_token'] ?? '';
    $name    = trim($_POST['name'] ?? '');
    $upiId   = trim($_POST['upi_id'] ?? '');

    if (!verifyCSRFToken($token)) {
        header("Location: group?id=" . $groupId . "&error=" . urlencode("Invalid security token."));
        exit;
    }

    if (empty($name)) {
        header("Location: group?id=" . $groupId . "&error=" . urlencode("Participant name cannot be empty."));
        exit;
    }

    $success = addParticipant($groupId, $name, $upiId);
    if ($success) {
        header("Location: group?id=" . $groupId . "&msg=" . urlencode("'" . $name . "' added to group."));
    } else {
        header("Location: group?id=" . $groupId . "&error=" . urlencode("Participant already exists or could not be added."));
    }
    exit;
}

header("Location: dashboard");
exit;
