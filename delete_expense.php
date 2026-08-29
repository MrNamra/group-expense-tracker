<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$expenseId = (int) ($_GET['id'] ?? 0);
$groupId = (int) ($_GET['group_id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!verifyCSRFToken($token)) {
    header("Location: group?id=" . $groupId . "&error=" . urlencode("Invalid security token."));
    exit;
}

if (!isGroupOwner($groupId, $currentUser['id'])) {
    header("Location: group?id=" . $groupId . "&error=" . urlencode("Only the group owner can delete expenses."));
    exit;
}

$success = deleteExpense($expenseId, $groupId);
if ($success) {
    header("Location: group?id=" . $groupId . "&msg=" . urlencode("Expense deleted successfully."));
} else {
    header("Location: group?id=" . $groupId . "&error=" . urlencode("Failed to delete expense."));
}
exit;
