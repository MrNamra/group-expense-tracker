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
        $group['upi_id'] = $group['upi_id'] ?? '';
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
// ─── USER CONTACTS (Universal Friends Book) ──────────────────────────────

// Get all contacts for a user (sorted alphabetically)
function getUserContacts(int $userId): array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM user_contacts WHERE user_id = :uid ORDER BY name ASC");
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

// Save or update a contact for a user (universal driver-agnostic upsert)
function saveUserContact(int $userId, string $name, string $upiId = ''): void {
    $name = trim($name);
    if (empty($name)) return;
    $upiId = trim($upiId);

    $db = getDBConnection();
    // Check if contact already exists for this user
    $checkStmt = $db->prepare("SELECT id, upi_id FROM user_contacts WHERE user_id = :uid AND LOWER(name) = LOWER(:name)");
    $checkStmt->execute([':uid' => $userId, ':name' => $name]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        if (!empty($upiId)) {
            $stmt = $db->prepare("UPDATE user_contacts SET upi_id = :upi WHERE id = :id");
            $stmt->execute([':upi' => $upiId, ':id' => $existing['id']]);
        }
    } else {
        $stmt = $db->prepare("INSERT INTO user_contacts (user_id, name, upi_id) VALUES (:uid, :name, :upi)");
        $stmt->execute([':uid' => $userId, ':name' => $name, ':upi' => $upiId ?: null]);
    }
}

// Update a contact's UPI ID (called when participant UPI is set)
function updateContactUpi(int $userId, string $name, string $upiId): void {
    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE user_contacts SET upi_id = :upi WHERE user_id = :uid AND name = :name");
    $stmt->execute([':upi' => $upiId ?: null, ':uid' => $userId, ':name' => $name]);
    // Also insert if not exists
    saveUserContact($userId, $name, $upiId);
}

// Get a single contact by ID for a user
function getUserContactById(int $userId, int $contactId): ?array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM user_contacts WHERE id = :id AND user_id = :uid");
    $stmt->execute([':id' => $contactId, ':uid' => $userId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

// Update a contact's name and UPI ID
function updateUserContact(int $userId, int $contactId, string $name, string $upiId): bool {
    $name = trim($name);
    if (empty($name)) return false;
    $db = getDBConnection();
    // Check if another contact of same user already has this name
    $stmtCheck = $db->prepare("SELECT id FROM user_contacts WHERE user_id = :uid AND LOWER(name) = LOWER(:name) AND id != :id");
    $stmtCheck->execute([':uid' => $userId, ':name' => $name, ':id' => $contactId]);
    if ($stmtCheck->fetch()) {
        return false; // Name conflict
    }

    $stmt = $db->prepare("UPDATE user_contacts SET name = :name, upi_id = :upi WHERE id = :id AND user_id = :uid");
    return $stmt->execute([
        ':name' => $name,
        ':upi'  => trim($upiId) ?: null,
        ':id'   => $contactId,
        ':uid'  => $userId
    ]);
}

// Delete a contact
function deleteUserContact(int $userId, int $contactId): bool {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM user_contacts WHERE id = :id AND user_id = :uid");
    return $stmt->execute([':id' => $contactId, ':uid' => $userId]);
}

// ─── GROUP FUNCTIONS ──────────────────────────────────────────────────────


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

        // Add additional participants (support 'name||upi_id' packed format from contacts picker)
        $stmtPartUpi = $db->prepare("INSERT INTO participants (group_id, name, upi_id, user_id) VALUES (:group_id, :name, :upi_id, NULL)");
        foreach ($participantNames as $packed) {
            $packed = trim($packed);
            if (empty($packed)) continue;

            // Unpack 'name||upi_id' if present (sent from contacts picker)
            if (str_contains($packed, '||')) {
                [$pName, $pUpi] = explode('||', $packed, 2);
            } else {
                $pName = $packed;
                $pUpi  = '';
            }
            $pName = trim($pName);
            if (empty($pName) || strtolower($pName) === strtolower($owner['username'] ?? '')) continue;

            $stmtPartUpi->execute([
                ':group_id' => $groupId,
                ':name'     => $pName,
                ':upi_id'   => $pUpi ?: null
            ]);
            // Auto-save to owner's contact book
            saveUserContact($ownerId, $pName, $pUpi);
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

// Add participant to group — auto-saves to owner's contact book
function addParticipant(int $groupId, string $name, string $upiId = ''): bool {
    $name = trim($name);
    if (empty($name)) return false;

    $db = getDBConnection();

    // Check if participant already exists in group
    $stmt = $db->prepare("SELECT id FROM participants WHERE group_id = :group_id AND LOWER(name) = LOWER(:name)");
    $stmt->execute([':group_id' => $groupId, ':name' => $name]);
    if ($stmt->fetch()) {
        return false; // Duplicate name in group
    }

    $stmt = $db->prepare("INSERT INTO participants (group_id, name, upi_id) VALUES (:group_id, :name, :upi_id)");
    $ok = $stmt->execute([':group_id' => $groupId, ':name' => $name, ':upi_id' => $upiId ?: null]);

    // Auto-save to the group owner's contact list
    if ($ok) {
        $grpStmt = $db->prepare("SELECT owner_id FROM groups WHERE id = :gid");
        $grpStmt->execute([':gid' => $groupId]);
        $grp = $grpStmt->fetch();
        if ($grp) {
            saveUserContact((int)$grp['owner_id'], $name, $upiId);
        }
    }

    return $ok;
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

// Get paid settlements for a group
function getPaidSettlements(int $groupId): array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT from_name, to_name FROM paid_settlements WHERE group_id = :gid");
    $stmt->execute([':gid' => $groupId]);
    $rows = $stmt->fetchAll();
    $keys = [];
    foreach ($rows as $r) {
        $keys[$r['from_name'] . '|||' . $r['to_name']] = true;
    }
    return $keys;
}

// Compute group statistics and debt settlements ("Who will pay to whom and how much")
function calculateGroupStats(int $groupId, array $paidSettlements = []): array {
    $participants = getGroupParticipants($groupId);
    $expenses = getGroupExpenses($groupId);

    $totalExpense = 0.0;
    $balances = []; // participant_id => ['name' => ..., 'paid' => 0.0, 'share' => 0.0, 'net' => 0.0]

    // Build name => upi_id map for quick lookup
    $upiMap = [];
    foreach ($participants as $p) {
        $balances[$p['id']] = [
            'id'    => $p['id'],
            'name'  => $p['name'],
            'paid'  => 0.0,
            'share' => 0.0,
            'net'   => 0.0
        ];
        $upiMap[$p['name']] = $p['upi_id'] ?? '';
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
    $settlements = []; // list of ['from' => name, 'to' => name, 'amount' => float, 'paid' => bool]

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
            $key = $debtor['name'] . '|||' . $creditor['name'];
            $settlements[] = [
                'from'   => $debtor['name'],
                'to'     => $creditor['name'],
                'amount' => $payment,
                'paid'   => isset($paidSettlements[$key]),
                'to_upi' => $upiMap[$creditor['name']] ?? ''
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
        'total_expense'       => $totalExpense,
        'individual_balances' => array_values($balances),
        'settlements'         => $settlements
    ];
}

// Activity logging disabled for maximum performance and zero database overhead
function logActivity(?int $userId, string $action, ?string $details = null): void {
    // No-op: all activity is naturally visible via core primary tables (users, groups, expenses)
}

// ─── DYNAMIC PAYMENT APPS ───────────────────────────────────────────────────

function getActivePaymentApps(): array {
    $db = getDBConnection();
    try {
        $stmt = $db->query("SELECT * FROM payment_apps WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($apps)) {
            return $apps;
        }
    } catch (Exception $e) {}

    return getDefaultPaymentApps();
}

function getAllPaymentApps(): array {
    $db = getDBConnection();
    try {
        $stmt = $db->query("SELECT * FROM payment_apps ORDER BY sort_order ASC, id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getPaymentAppById(int $id): ?array {
    $db = getDBConnection();
    try {
        $stmt = $db->prepare("SELECT * FROM payment_apps WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        return $app ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function togglePaymentApp(int $id): bool {
    $db = getDBConnection();
    try {
        $stmt = $db->prepare("UPDATE payment_apps SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    } catch (Exception $e) {
        return false;
    }
}

function deletePaymentApp(int $id): bool {
    $db = getDBConnection();
    try {
        $stmt = $db->prepare("DELETE FROM payment_apps WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    } catch (Exception $e) {
        return false;
    }
}

function savePaymentApp(array $data): array {
    $db = getDBConnection();
    $name      = trim($data['name'] ?? '');
    $code      = strtolower(preg_replace('/[^a-z0-9_]/', '', trim($data['app_code'] ?? '')));
    $uri       = trim($data['uri_prefix'] ?? '');
    $bg        = trim($data['bg_color'] ?? '#ffffff');
    $text      = trim($data['text_color'] ?? '#000000');
    $iconType  = in_array($data['icon_type'] ?? '', ['svg', 'emoji', 'text']) ? $data['icon_type'] : 'svg';
    $iconData  = trim($data['icon_data'] ?? '');
    $sortOrder = (int)($data['sort_order'] ?? 0);
    $isActive  = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $id        = !empty($data['id']) ? (int)$data['id'] : null;

    if (empty($name) || empty($code) || empty($uri)) {
        return ['success' => false, 'message' => 'App Name, Code, and URI Prefix are required.'];
    }

    try {
        if ($id) {
            $stmt = $db->prepare("
                UPDATE payment_apps 
                SET name = :name, app_code = :code, uri_prefix = :uri, icon_type = :itype,
                    icon_data = :idata, bg_color = :bg, text_color = :text, sort_order = :sort, is_active = :active
                WHERE id = :id
            ");
            $stmt->execute([
                ':name'   => $name,
                ':code'   => $code,
                ':uri'    => $uri,
                ':itype'  => $iconType,
                ':idata'  => $iconData,
                ':bg'     => $bg,
                ':text'   => $text,
                ':sort'   => $sortOrder,
                ':active' => $isActive,
                ':id'     => $id
            ]);
            return ['success' => true, 'message' => 'Payment app updated successfully.'];
        } else {
            // Check uniqueness of code
            $chk = $db->prepare("SELECT id FROM payment_apps WHERE app_code = :code");
            $chk->execute([':code' => $code]);
            if ($chk->fetch()) {
                return ['success' => false, 'message' => 'An app with code ' . htmlspecialchars($code) . ' already exists.'];
            }

            $stmt = $db->prepare("
                INSERT INTO payment_apps (name, app_code, uri_prefix, icon_type, icon_data, bg_color, text_color, sort_order, is_active)
                VALUES (:name, :code, :uri, :itype, :idata, :bg, :text, :sort, :active)
            ");
            $stmt->execute([
                ':name'   => $name,
                ':code'   => $code,
                ':uri'    => $uri,
                ':itype'  => $iconType,
                ':idata'  => $iconData,
                ':bg'     => $bg,
                ':text'   => $text,
                ':sort'   => $sortOrder,
                ':active' => $isActive
            ]);
            return ['success' => true, 'message' => 'Payment app added successfully.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function resetPaymentAppsToDefaults(): void {
    $db = getDBConnection();
    try {
        $db->exec("DELETE FROM payment_apps");
        $defaults = getDefaultPaymentApps();
        $stmt = $db->prepare("INSERT INTO payment_apps (name, app_code, uri_prefix, icon_type, icon_data, bg_color, text_color, sort_order, is_active) VALUES (:name, :code, :uri, :itype, :idata, :bg, :text, :sort, :active)");
        foreach ($defaults as $d) {
            $stmt->execute([
                ':name'   => $d['name'],
                ':code'   => $d['app_code'],
                ':uri'    => $d['uri_prefix'],
                ':itype'  => $d['icon_type'],
                ':idata'  => $d['icon_data'],
                ':bg'     => $d['bg_color'],
                ':text'   => $d['text_color'],
                ':sort'   => $d['sort_order'],
                ':active' => $d['is_active']
            ]);
        }
    } catch (Exception $e) {}
}

function getDefaultPaymentApps(): array {
    return [
        [
            'id' => 1,
            'name' => 'GPay',
            'app_code' => 'gpay',
            'uri_prefix' => 'gpay://upi/pay?',
            'icon_type' => 'svg',
            'icon_data' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/></svg>',
            'bg_color' => '#ffffff',
            'text_color' => '#1f2937',
            'sort_order' => 1,
            'is_active' => 1
        ],
        [
            'id' => 2,
            'name' => 'PhonePe',
            'app_code' => 'phonepe',
            'uri_prefix' => 'phonepe://pay?',
            'icon_type' => 'svg',
            'icon_data' => '<svg width="18" height="18" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" fill="#5F259F"/><path d="M19.5 8H12a1 1 0 0 0-1 1v2.5h3.2v-1.5h4.8c.8 0 1.5.7 1.5 1.5v1.2c0 .8-.7 1.5-1.5 1.5h-5.2a1 1 0 0 0-1 1v7.8a1 1 0 0 0 2 0v-4.8h3.2l3.4 5.2a1 1 0 0 0 1.7-1.1L21.4 17c1.7-.6 2.8-2.2 2.8-4v-1c0-2.2-1.8-4-4-4z" fill="#FFFFFF"/></svg>',
            'bg_color' => '#5f259f',
            'text_color' => '#ffffff',
            'sort_order' => 2,
            'is_active' => 1
        ],
        [
            'id' => 3,
            'name' => 'Paytm',
            'app_code' => 'paytm',
            'uri_prefix' => 'paytmmp://pay?',
            'icon_type' => 'svg',
            'icon_data' => '<svg width="26" height="14" viewBox="0 0 46 16" fill="none"><text x="0" y="13" font-size="13" font-weight="900" fill="#ffffff" font-family="sans-serif">Pay</text><text x="24" y="13" font-size="13" font-weight="900" fill="#00B9F5" font-family="sans-serif">tm</text></svg>',
            'bg_color' => '#002e6e',
            'text_color' => '#00b9f5',
            'sort_order' => 3,
            'is_active' => 1
        ],
        [
            'id' => 4,
            'name' => 'BHIM',
            'app_code' => 'bhim',
            'uri_prefix' => 'bhim://pay?',
            'icon_type' => 'svg',
            'icon_data' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="4" fill="#005792"/><path d="M6 18L14 6h4L10 18H6z" fill="#00A859"/><path d="M12 18L18 9h2l-6 9h-2z" fill="#FF8300"/></svg>',
            'bg_color' => '#005792',
            'text_color' => '#ffffff',
            'sort_order' => 4,
            'is_active' => 1
        ],
        [
            'id' => 5,
            'name' => 'Other UPI App',
            'app_code' => 'generic',
            'uri_prefix' => 'upi://pay?',
            'icon_type' => 'svg',
            'icon_data' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
            'bg_color' => '#1e293b',
            'text_color' => '#ffffff',
            'sort_order' => 5,
            'is_active' => 1
        ]
    ];
}

// ─── ADMIN DASHBOARD STATS & DATA ──────────────────────────────────────────

function getPlatformStats(): array {
    $db = getDBConnection();
    try {
        $totalUsers    = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalGroups   = (int)$db->query("SELECT COUNT(*) FROM `groups`")->fetchColumn();
        $totalExpenses = (int)$db->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
        $totalVolume   = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn();
        $totalSettled  = (int)$db->query("SELECT COUNT(*) FROM paid_settlements")->fetchColumn();
        $totalApps     = (int)$db->query("SELECT COUNT(*) FROM payment_apps")->fetchColumn();
        $activeApps    = (int)$db->query("SELECT COUNT(*) FROM payment_apps WHERE is_active = 1")->fetchColumn();

        return [
            'total_users'     => $totalUsers,
            'total_groups'    => $totalGroups,
            'total_expenses'  => $totalExpenses,
            'total_volume'    => $totalVolume,
            'total_settled'   => $totalSettled,
            'total_apps'      => $totalApps,
            'active_apps'     => $activeApps
        ];
    } catch (Exception $e) {
        return [
            'total_users' => 0, 'total_groups' => 0, 'total_expenses' => 0,
            'total_volume' => 0.0, 'total_settled' => 0, 'total_apps' => 0, 'active_apps' => 0
        ];
    }
}

function getAllUsersForAdmin(): array {
    $db = getDBConnection();
    try {
        $stmt = $db->query("
            SELECT u.id, u.username, u.email, u.is_admin, u.created_at,
                   (SELECT COUNT(*) FROM `groups` WHERE owner_id = u.id) as groups_count,
                   (SELECT COUNT(*) FROM expenses WHERE created_by = u.id) as expenses_count
            FROM users u
            ORDER BY u.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getAllGroupsForAdmin(): array {
    $db = getDBConnection();
    try {
        $stmt = $db->query("
            SELECT g.*, u.username as owner_username, u.email as owner_email,
                   (SELECT COUNT(*) FROM participants WHERE group_id = g.id) as participants_count,
                   (SELECT COUNT(*) FROM expenses WHERE group_id = g.id) as expenses_count,
                   (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE group_id = g.id) as total_spent
            FROM `groups` g
            JOIN users u ON g.owner_id = u.id
            ORDER BY g.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getAllExpensesForAdmin(int $limit = 100): array {
    $db = getDBConnection();
    try {
        $stmt = $db->query("
            SELECT e.*, g.name as group_name, g.currency as group_currency,
                   p.name as payer_name, u.username as creator_username
            FROM expenses e
            JOIN `groups` g ON e.group_id = g.id
            JOIN participants p ON e.payer_id = p.id
            JOIN users u ON e.created_by = u.id
            ORDER BY e.created_at DESC, e.id DESC
            LIMIT " . (int)$limit . "
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function updateUserByAdmin(int $userId, string $username, string $email, int $isAdmin): array {
    $db = getDBConnection();
    $username = trim($username);
    $email = strtolower(trim($email));

    if (empty($username) || empty($email)) {
        return ['success' => false, 'message' => 'Username and email cannot be empty.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format.'];
    }

    // Check duplicate username or email on another user
    $chk = $db->prepare("SELECT id FROM users WHERE (username = :u OR email = :e) AND id != :id");
    $chk->execute([':u' => $username, ':e' => $email, ':id' => $userId]);
    if ($chk->fetch()) {
        return ['success' => false, 'message' => 'Username or email already in use by another user.'];
    }

    try {
        $stmt = $db->prepare("UPDATE users SET username = :u, email = :e, is_admin = :a WHERE id = :id");
        $stmt->execute([
            ':u'  => $username,
            ':e'  => $email,
            ':a'  => $isAdmin ? 1 : 0,
            ':id' => $userId
        ]);
        return ['success' => true, 'message' => 'User details updated successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function changeUserPasswordByAdmin(int $userId, string $newPassword): array {
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }

    $db = getDBConnection();
    try {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = :p WHERE id = :id");
        $stmt->execute([':p' => $hashed, ':id' => $userId]);
        return ['success' => true, 'message' => 'Password updated successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function deleteUserByAdmin(int $userId): array {
    $db = getDBConnection();
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        return ['success' => true, 'message' => 'User deleted successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()];
    }
}

