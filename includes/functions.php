<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Format currency display
function formatMoney($amount, ?string $currency = null): string {
    $symbol = $currency ?: APP_CURRENCY_SYMBOL;
    return $symbol . ' ' . number_format((float)$amount, 2);
}

// Get group by ID
function getGroupById(int $groupId): ?array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT g.*, u.username as owner_name FROM groups g JOIN users u ON g.owner_id = u.id WHERE g.id = :id");
    $stmt->execute([':id' => $groupId]);
    $group = $stmt->fetch();
    if ($group) {
        $group['currency'] = $group['currency'] ?: APP_CURRENCY_SYMBOL;
    }
    return $group ?: null;
}

// Get group by share token (Public access)
function getGroupByToken(string $token): ?array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT g.*, u.username as owner_name FROM groups g JOIN users u ON g.owner_id = u.id WHERE g.share_token = :token");
    $stmt->execute([':token' => $token]);
    $group = $stmt->fetch();
    if ($group) {
        $group['currency'] = $group['currency'] ?: APP_CURRENCY_SYMBOL;
    }
    return $group ?: null;
}

// Get all groups for user (either owned or added as participant)
function getUserGroups(int $userId): array {
    $db = getDBConnection();
    // User is owner or linked participant or has same email
    $stmt = $db->prepare("
        SELECT DISTINCT g.*, u.username as owner_name, 
               (SELECT COUNT(*) FROM expenses WHERE group_id = g.id) as total_expenses_count,
               (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE group_id = g.id) as total_spent
        FROM groups g
        JOIN users u ON g.owner_id = u.id
        LEFT JOIN participants p ON p.group_id = g.id
        WHERE g.owner_id = :user_id OR p.user_id = :user_id
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([':user_id' => $userId]);
    $groups = $stmt->fetchAll();
    foreach ($groups as &$g) {
        $g['currency'] = $g['currency'] ?: APP_CURRENCY_SYMBOL;
    }
    return $groups;
}

// Create new group
function createGroup(int $ownerId, string $name, string $description, array $participantNames = [], string $currency = '₹'): int {
    $db = getDBConnection();
    $db->beginTransaction();

    try {
        $token = generateGroupShareToken();
        $stmt = $db->prepare("INSERT INTO groups (owner_id, name, description, share_token, currency) VALUES (:owner_id, :name, :description, :share_token, :currency)");
        $stmt->execute([
            ':owner_id' => $ownerId,
            ':name' => trim($name),
            ':description' => trim($description),
            ':share_token' => $token,
            ':currency' => trim($currency) ?: '₹'
        ]);

        $groupId = (int) $db->lastInsertId();

        // Get owner details
        $stmtOwner = $db->prepare("SELECT username FROM users WHERE id = :id");
        $stmtOwner->execute([':id' => $ownerId]);
        $owner = $stmtOwner->fetch();

        // Automatically add owner as first participant
        $stmtPart = $db->prepare("INSERT INTO participants (group_id, name, user_id) VALUES (:group_id, :name, :user_id)");
        $stmtPart->execute([
            ':group_id' => $groupId,
            ':name' => $owner ? $owner['username'] : 'Owner',
            ':user_id' => $ownerId
        ]);

        // Add additional participant names provided during group creation
        foreach ($participantNames as $pName) {
            $pName = trim($pName);
            if (!empty($pName) && strtolower($pName) !== strtolower($owner['username'] ?? '')) {
                $stmtPart->execute([
                    ':group_id' => $groupId,
                    ':name' => $pName,
                    ':user_id' => null
                ]);
            }
        }

        $db->commit();
        return $groupId;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// Get group participants
function getGroupParticipants(int $groupId): array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM participants WHERE group_id = :group_id ORDER BY id ASC");
    $stmt->execute([':group_id' => $groupId]);
    return $stmt->fetchAll();
}

// Add participant to group
function addParticipant(int $groupId, string $name): bool {
    $name = trim($name);
    if (empty($name)) return false;

    $db = getDBConnection();
    
    // Check if participant already exists in group
    $stmt = $db->prepare("SELECT id FROM participants WHERE group_id = :group_id AND LOWER(name) = LOWER(:name)");
    $stmt->execute([':group_id' => $groupId, ':name' => $name]);
    if ($stmt->fetch()) {
        return false; // Duplicate name in group
    }

    $stmt = $db->prepare("INSERT INTO participants (group_id, name) VALUES (:group_id, :name)");
    return $stmt->execute([':group_id' => $groupId, ':name' => $name]);
}

// Delete participant from group (only if not used in expenses or splits)
function deleteParticipant(int $groupId, int $participantId): array {
    $db = getDBConnection();
    
    // Check if participant paid for expenses or is part of splits
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM expenses WHERE payer_id = :pid");
    $stmt->execute([':pid' => $participantId]);
    if ($stmt->fetch()['cnt'] > 0) {
        return ['success' => false, 'message' => 'Cannot delete participant who has paid for an expense. Delete their expenses first.'];
    }

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM expense_splits WHERE participant_id = :pid");
    $stmt->execute([':pid' => $participantId]);
    if ($stmt->fetch()['cnt'] > 0) {
        return ['success' => false, 'message' => 'Cannot delete participant who is part of expense splits. Remove them from expenses first.'];
    }

    $stmt = $db->prepare("DELETE FROM participants WHERE id = :pid AND group_id = :gid");
    $stmt->execute([':pid' => $participantId, ':gid' => $groupId]);
    return ['success' => true, 'message' => 'Participant deleted successfully.'];
}

// Add expense with split between selected participants
function addExpense(int $groupId, string $title, float $amount, int $payerId, array $splitParticipantIds, string $expenseDate, int $createdBy): bool {
    if (empty($title) || $amount <= 0 || empty($payerId) || empty($splitParticipantIds)) {
        return false;
    }

    $db = getDBConnection();
    $db->beginTransaction();

    try {
        // Insert main expense record
        $stmt = $db->prepare("INSERT INTO expenses (group_id, title, amount, payer_id, created_by, expense_date) VALUES (:gid, :title, :amount, :payer_id, :created_by, :expense_date)");
        $stmt->execute([
            ':gid' => $groupId,
            ':title' => trim($title),
            ':amount' => $amount,
            ':payer_id' => $payerId,
            ':created_by' => $createdBy,
            ':expense_date' => $expenseDate ?: date('Y-m-d')
        ]);
        $expenseId = (int) $db->lastInsertId();

        // Calculate equal split amount for selected participants
        $count = count($splitParticipantIds);
        $baseSplit = floor(($amount / $count) * 100) / 100;
        $remainder = round(($amount - ($baseSplit * $count)), 2);

        $stmtSplit = $db->prepare("INSERT INTO expense_splits (expense_id, participant_id, split_amount) VALUES (:expense_id, :participant_id, :split_amount)");
        
        $i = 0;
        foreach ($splitParticipantIds as $pId) {
            // Allocate remainder cent(s) to first participant(s) to guarantee sum equals exact amount
            $pAmount = $baseSplit + ($i < (int)round($remainder * 100) ? 0.01 : 0.00);
            $stmtSplit->execute([
                ':expense_id' => $expenseId,
                ':participant_id' => (int)$pId,
                ':split_amount' => $pAmount
            ]);
            $i++;
        }

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

// Update expense
function updateExpense(int $expenseId, int $groupId, string $title, float $amount, int $payerId, array $splitParticipantIds, string $expenseDate): bool {
    if (empty($title) || $amount <= 0 || empty($payerId) || empty($splitParticipantIds)) {
        return false;
    }

    $db = getDBConnection();
    $db->beginTransaction();

    try {
        $stmt = $db->prepare("UPDATE expenses SET title = :title, amount = :amount, payer_id = :payer_id, expense_date = :expense_date WHERE id = :id AND group_id = :gid");
        $stmt->execute([
            ':title' => trim($title),
            ':amount' => $amount,
            ':payer_id' => $payerId,
            ':expense_date' => $expenseDate,
            ':id' => $expenseId,
            ':gid' => $groupId
        ]);

        // Delete old splits
        $stmtDel = $db->prepare("DELETE FROM expense_splits WHERE expense_id = :eid");
        $stmtDel->execute([':eid' => $expenseId]);

        // Re-insert new splits
        $count = count($splitParticipantIds);
        $baseSplit = floor(($amount / $count) * 100) / 100;
        $remainder = round(($amount - ($baseSplit * $count)), 2);

        $stmtSplit = $db->prepare("INSERT INTO expense_splits (expense_id, participant_id, split_amount) VALUES (:expense_id, :participant_id, :split_amount)");
        
        $i = 0;
        foreach ($splitParticipantIds as $pId) {
            $pAmount = $baseSplit + ($i < (int)round($remainder * 100) ? 0.01 : 0.00);
            $stmtSplit->execute([
                ':expense_id' => $expenseId,
                ':participant_id' => (int)$pId,
                ':split_amount' => $pAmount
            ]);
            $i++;
        }

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

// Delete expense
function deleteExpense(int $expenseId, int $groupId): bool {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM expenses WHERE id = :id AND group_id = :gid");
    return $stmt->execute([':id' => $expenseId, ':gid' => $groupId]);
}

// Fetch single expense details with splits
function getExpenseDetails(int $expenseId, int $groupId): ?array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT e.*, p.name as payer_name FROM expenses e JOIN participants p ON e.payer_id = p.id WHERE e.id = :id AND e.group_id = :gid");
    $stmt->execute([':id' => $expenseId, ':gid' => $groupId]);
    $expense = $stmt->fetch();

    if (!$expense) return null;

    $stmtSplits = $db->prepare("SELECT participant_id, split_amount FROM expense_splits WHERE expense_id = :eid");
    $stmtSplits->execute([':eid' => $expenseId]);
    $expense['splits'] = $stmtSplits->fetchAll();
    $expense['split_participant_ids'] = array_column($expense['splits'], 'participant_id');

    return $expense;
}

// Get all expenses for group with split participant names
function getGroupExpenses(int $groupId): array {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT e.*, p.name as payer_name 
        FROM expenses e 
        JOIN participants p ON e.payer_id = p.id 
        WHERE e.group_id = :gid 
        ORDER BY e.expense_date DESC, e.id DESC
    ");
    $stmt->execute([':gid' => $groupId]);
    $expenses = $stmt->fetchAll();

    foreach ($expenses as &$exp) {
        $stmtSplits = $db->prepare("
            SELECT es.split_amount, p.name as participant_name 
            FROM expense_splits es 
            JOIN participants p ON es.participant_id = p.id 
            WHERE es.expense_id = :eid
        ");
        $stmtSplits->execute([':eid' => $exp['id']]);
        $exp['split_details'] = $stmtSplits->fetchAll();
    }

    return $expenses;
}

// Compute group statistics and debt settlements ("Who will pay to whom and how much")
function calculateGroupStats(int $groupId): array {
    $participants = getGroupParticipants($groupId);
    $expenses = getGroupExpenses($groupId);

    $totalExpense = 0.0;
    $balances = []; // participant_id => ['name' => ..., 'paid' => 0.0, 'share' => 0.0, 'net' => 0.0]

    foreach ($participants as $p) {
        $balances[$p['id']] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'paid' => 0.0,
            'share' => 0.0,
            'net' => 0.0
        ];
    }

    foreach ($expenses as $e) {
        $totalExpense += (float)$e['amount'];
        
        // Add paid amount
        if (isset($balances[$e['payer_id']])) {
            $balances[$e['payer_id']]['paid'] += (float)$e['amount'];
        }

        // Add split shares
        foreach ($e['split_details'] as $sd) {
            // Find participant ID matching name or match directly
            foreach ($participants as $p) {
                if ($p['name'] === $sd['participant_name']) {
                    $balances[$p['id']]['share'] += (float)$sd['split_amount'];
                    break;
                }
            }
        }
    }

    // Compute net balance (net = paid - share)
    $debtors = [];  // net < 0 (owes money)
    $creditors = []; // net > 0 (owed money)

    foreach ($balances as $id => &$b) {
        $b['net'] = round($b['paid'] - $b['share'], 2);
        if ($b['net'] < -0.009) {
            $debtors[] = ['id' => $id, 'name' => $b['name'], 'amount' => abs($b['net'])];
        } elseif ($b['net'] > 0.009) {
            $creditors[] = ['id' => $id, 'name' => $b['name'], 'amount' => $b['net']];
        }
    }

    // Debt Simplification Algorithm
    $settlements = []; // list of ['from' => name, 'to' => name, 'amount' => float]

    // Sort debtors and creditors descending by amount
    usort($debtors, fn($a, $b) => $b['amount'] <=> $a['amount']);
    usort($creditors, fn($a, $b) => $b['amount'] <=> $a['amount']);

    $dIdx = 0;
    $cIdx = 0;

    while ($dIdx < count($debtors) && $cIdx < count($creditors)) {
        $debtor = &$debtors[$dIdx];
        $creditor = &$creditors[$cIdx];

        $payment = min($debtor['amount'], $creditor['amount']);
        $payment = round($payment, 2);

        if ($payment > 0) {
            $settlements[] = [
                'from' => $debtor['name'],
                'to' => $creditor['name'],
                'amount' => $payment
            ];
        }

        $debtor['amount'] = round($debtor['amount'] - $payment, 2);
        $creditor['amount'] = round($creditor['amount'] - $payment, 2);

        if ($debtor['amount'] < 0.01) {
            $dIdx++;
        }
        if ($creditor['amount'] < 0.01) {
            $cIdx++;
        }
    }

    return [
        'total_expense' => $totalExpense,
        'individual_balances' => array_values($balances),
        'settlements' => $settlements
    ];
}
