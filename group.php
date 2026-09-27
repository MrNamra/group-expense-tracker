<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$groupId = (int) ($_GET['id'] ?? 0);
$group = getGroupById($groupId);

if (!$group) {
    header("Location: dashboard?msg=" . urlencode("Group not found."));
    exit;
}

$currentUser = getCurrentUser();
$isOwner = $currentUser && isGroupOwner($groupId, $currentUser['id']);
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

$participants = getGroupParticipants($groupId);
$expenses = getGroupExpenses($groupId);
$paidSettlements = getPaidSettlements($groupId);
$stats = calculateGroupStats($groupId, $paidSettlements);

// Build participant name => upi_id map for template
$participantUpiMap = [];
foreach ($participants as $p) {
    $participantUpiMap[$p['name']] = $p['upi_id'] ?? '';
}

$publicUrl = APP_URL . "/public?token=" . urlencode($group['share_token']);

$pageTitle = $group['name'];
$pageDescription = "Track expenses, split bills, and view debt settlements for " . $group['name'] . ".";

require_once __DIR__ . '/includes/header.php';
?>

<!-- Group Header Banner -->
<div class="group-header-banner">
    <div class="banner-top">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.4rem; flex-wrap: wrap;">
                <h1 style="font-size: 1.6rem; font-weight: 800; margin: 0;"><?= e($group['name']) ?></h1>
                <?php if ($isOwner): ?>
                    <span class="badge-owner">You are Owner</span>
                <?php endif; ?>
            </div>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;"><?= e($group['description'] ?: 'No description.') ?></p>
            <small style="color: var(--text-dim);">Created by <strong><?= e($group['owner_name']) ?></strong> on <?= date('M d, Y', strtotime($group['created_at'])) ?></small>
        </div>

        <div class="banner-actions">
            <div class="share-box">
                <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); white-space: nowrap;">Share:</span>
                <input type="text" id="shareUrlInput" class="share-input" value="<?= e($publicUrl) ?>" readonly>
                <button type="button" id="copyShareUrlBtn" class="btn btn-secondary btn-sm">📋</button>
            </div>

            <?php if ($isOwner): ?>
                <div class="owner-action-btns">
                    <a href="edit_group?id=<?= $groupId ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                    <a href="delete_group?id=<?= $groupId ?>&csrf_token=<?= e($csrfToken) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this group and all its expenses?');">🗑️ Delete</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success"><span>✅</span> <?= e($msg) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><span>⚠️</span> <?= e($error) ?></div>
<?php endif; ?>

<div class="group-content-grid">
    <!-- ── Left Column ── -->
    <div class="group-left-col">
        <!-- Expenses header -->
        <div class="page-header" style="margin-bottom: 1rem;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700;">Group Expenses</h2>
                <p style="font-size: 0.82rem; color: var(--text-muted);"><?= count($expenses) ?> expenses logged</p>
            </div>
            <?php if (isLoggedIn()): ?>
                <a href="add_expense?group_id=<?= $groupId ?>" class="btn btn-primary btn-sm"><span>➕</span> Add Expense</a>
            <?php endif; ?>
        </div>

        <!-- Scrollable Expenses Frame -->
        <div class="expenses-scroll-frame">
            <?php if (empty($expenses)): ?>
                <div style="text-align: center; padding: 2.5rem 1rem;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🧾</div>
                    <h4 style="margin-bottom: 0.4rem;">No Expenses Added Yet</h4>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1.2rem;">Start adding expenses and split them with your group.</p>
                    <?php if (isLoggedIn()): ?>
                        <a href="add_expense?group_id=<?= $groupId ?>" class="btn btn-primary btn-sm"><span>➕</span> Add First Expense</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($expenses as $exp): ?>
                    <div class="expense-item">
                        <div class="expense-info">
                            <div class="expense-date-badge">
                                <div class="date-day"><?= date('d', strtotime($exp['expense_date'])) ?></div>
                                <div class="date-month"><?= date('M', strtotime($exp['expense_date'])) ?></div>
                            </div>
                            <div>
                                <h3 class="expense-title"><?= e($exp['title']) ?></h3>
                                <div class="expense-sub">
                                    Paid by <strong><?= e($exp['payer_name']) ?></strong> •
                                    <em><?= implode(', ', array_map(fn($sd) => e($sd['participant_name']), $exp['split_details'])) ?></em>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0;">
                            <div class="expense-amount"><?= formatMoney($exp['amount'], $group['currency']) ?></div>
                            <?php if ($isOwner): ?>
                                <div class="expense-actions">
                                    <a href="edit_expense?id=<?= $exp['id'] ?>&group_id=<?= $groupId ?>" class="btn btn-secondary btn-icon btn-sm" title="Edit">✏️</a>
                                    <a href="delete_expense?id=<?= $exp['id'] ?>&group_id=<?= $groupId ?>&csrf_token=<?= e($csrfToken) ?>" class="btn btn-danger btn-icon btn-sm" title="Delete" onclick="return confirm('Delete this expense?');">🗑️</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Participants Card -->
        <div class="card" style="margin-top: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700;">👥 Participants (<?= count($participants) ?>)</h3>
            </div>

            <?php if (isLoggedIn()):
                $userContacts = isLoggedIn() ? getUserContacts($currentUser['id']) : [];
                // Filter out contacts already in the group
                $groupNames = array_map(fn($p) => strtolower($p['name']), $participants);
                $availableContacts = array_filter($userContacts, fn($c) => !in_array(strtolower($c['name']), $groupNames));
            ?>
                <!-- Quick-add from contacts (if any available) -->
                <?php if (!empty($availableContacts)): ?>
                    <div class="contacts-quick-add">
                        <div class="contacts-quick-label">⚡ Quick add from contacts:</div>
                        <div class="contacts-quick-chips">
                            <?php foreach ($availableContacts as $c): ?>
                                <form action="add_participant" method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="group_id" value="<?= $groupId ?>">
                                    <input type="hidden" name="name" value="<?= e($c['name']) ?>">
                                    <input type="hidden" name="upi_id" value="<?= e($c['upi_id'] ?? '') ?>">
                                    <button type="submit" class="quick-contact-chip" title="Add <?= e($c['name']) ?>">
                                        <span class="contact-chip-avatar-sm"><?= strtoupper(mb_substr($c['name'], 0, 1)) ?></span>
                                        <span><?= e($c['name']) ?></span>
                                        <?php if (!empty($c['upi_id'])): ?><span style="font-size:0.7rem;">💳</span><?php endif; ?>
                                        <span class="quick-chip-plus">+</span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Manual add row -->
                <div class="manual-add-group-row" id="manualAddGroupRow" style="<?= !empty($availableContacts) ? 'margin-top:0.75rem;' : '' ?>">
                    <?php if (!empty($availableContacts)): ?>
                        <button type="button" class="btn-toggle-manual-add btn-sm" id="toggleManualAddBtn" onclick="toggleGroupManualAdd()">
                            ✏️ Add new person manually
                        </button>
                        <div id="groupManualAddFields" style="display:none; margin-top:0.6rem;">
                    <?php endif; ?>

                        <form action="add_participant" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="group_id" value="<?= $groupId ?>">
                            <div class="upi-inline-row">
                                <input type="text" name="name" class="form-control upi-input" placeholder="Name..." required style="flex:1.5;">
                                <input type="text" name="upi_id" class="form-control upi-input" placeholder="UPI ID (optional)" style="flex:2;">
                                <button type="submit" class="btn btn-primary btn-sm">➕ Add</button>
                            </div>
                        </form>

                    <?php if (!empty($availableContacts)): ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Participant list with UPI ID support -->
            <div class="participant-list">
                <?php foreach ($participants as $p): ?>
                    <div class="participant-row">
                        <div class="participant-chip">
                            <span class="participant-avatar"><?= strtoupper(mb_substr($p['name'], 0, 1)) ?></span>
                            <div class="participant-info">
                                <span class="participant-name"><?= e($p['name']) ?></span>
                                <?php if (!empty($p['upi_id'])): ?>
                                    <span class="participant-upi-badge">💳 <?= e($p['upi_id']) ?></span>
                                <?php else: ?>
                                    <span class="participant-upi-missing">No UPI set</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="participant-actions">
                            <?php if ($isOwner): ?>
                                <!-- UPI Set Toggle -->
                                <button class="btn-upi-set btn-sm" onclick="toggleUpiForm(<?= $p['id'] ?>)" title="Set UPI ID">
                                    <?= !empty($p['upi_id']) ? '✏️' : '💳' ?>
                                </button>
                                <?php if (count($participants) > 1): ?>
                                    <a href="delete_participant?id=<?= $p['id'] ?>&group_id=<?= $groupId ?>&csrf_token=<?= e($csrfToken) ?>" class="btn-remove-participant" onclick="return confirm('Remove <?= e($p['name']) ?>?');" title="Remove">×</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Inline UPI form (owner only, toggleable) -->
                    <?php if ($isOwner): ?>
                        <div class="upi-inline-form" id="upi-form-<?= $p['id'] ?>" style="display:none;">
                            <form action="update_participant_upi" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="group_id" value="<?= $groupId ?>">
                                <input type="hidden" name="participant_id" value="<?= $p['id'] ?>">
                                <div class="upi-inline-row">
                                    <input type="text" name="upi_id" class="form-control upi-input"
                                           value="<?= e($p['upi_id'] ?? '') ?>"
                                           placeholder="e.g. name@upi, 9000000000@paytm">
                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleUpiForm(<?= $p['id'] ?>)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <?php if ($isOwner): ?>
                <p style="font-size: 0.78rem; color: var(--text-dim); margin-top: 0.75rem; text-align: center;">
                    💡 Click 💳 next to any participant to set their UPI ID for payment QR codes.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right Column ── -->
    <div class="group-right-col">

        <!-- Expense Summary -->
        <div class="settlement-card">
            <h3 class="summary-section-title">📊 Expense Summary</h3>

            <div class="summary-total-box">
                <div class="summary-label">Total Group Expense</div>
                <div class="summary-amount"><?= formatMoney($stats['total_expense'], $group['currency']) ?></div>
            </div>

            <!-- Settle Up Plan -->
            <h4 class="settle-subtitle">🤝 Final Settle Up Plan</h4>

            <?php if (empty($stats['settlements'])): ?>
                <div class="settle-all-clear">✨ Everyone is settled up!</div>
            <?php else: ?>
                <?php foreach ($stats['settlements'] as $idx => $st): ?>
                    <?php
                    $toUpi   = $st['to_upi'] ?? '';
                    $hasUpi  = !empty($toUpi) && !$st['paid'];
                    $upiParams = $hasUpi
                        ? "pa=" . urlencode($toUpi) . "&pn=" . urlencode($st['to']) . "&cu=INR&am=" . number_format($st['amount'], 2, '.', '') . "&tn=" . urlencode($st['from'] . " pays " . $st['to'])
                        : '';
                    $upiStr     = $hasUpi ? "upi://pay?" . $upiParams : '';
                    $gpayUrl    = $hasUpi ? "gpay://upi/pay?" . $upiParams : '';
                    $phonepeUrl = $hasUpi ? "phonepe://pay?" . $upiParams : '';
                    $paytmUrl   = $hasUpi ? "paytmmp://pay?" . $upiParams : '';
                    $bhimUrl    = $hasUpi ? "bhim://pay?" . $upiParams : '';
                    $qrUrl      = $hasUpi
                        ? "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($upiStr)
                        : '';
                    ?>
                    <div class="settlement-item <?= $st['paid'] ? 'settlement-paid' : '' ?>">
                        <!-- Debtor → Creditor row -->
                        <div class="settlement-main-row">
                            <div class="settlement-info">
                                <strong><?= e($st['from']) ?></strong>
                                <span class="settlement-arrow">➔</span>
                                <strong><?= e($st['to']) ?></strong>
                            </div>
                            <div class="settlement-right">
                                <?php if ($st['paid']): ?>
                                    <span class="paid-badge">✅ Paid</span>
                                    <span class="settlement-amount strike"><?= $group['currency'] ?> 0.00</span>
                                <?php else: ?>
                                    <span class="settlement-amount"><?= formatMoney($st['amount'], $group['currency']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action row for settled items: Owner Undo Button -->
                        <?php if ($st['paid']): ?>
                            <?php if ($isOwner): ?>
                                <div class="settlement-actions-row settlement-paid-actions" style="border-top:1px dashed #bbf7d0; justify-content:space-between;">
                                    <span style="font-size:0.75rem; color:#15803d; font-weight:700;">Settlement completed</span>
                                    <form action="mark_paid" method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="action" value="unmark">
                                        <input type="hidden" name="group_id" value="<?= $groupId ?>">
                                        <input type="hidden" name="from_name" value="<?= e($st['from']) ?>">
                                        <input type="hidden" name="to_name" value="<?= e($st['to']) ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm"
                                                onclick="return confirm('Undo paid settlement for <?= e($st['from']) ?> to <?= e($st['to']) ?>?');"
                                                style="font-size:0.75rem; padding:0.25rem 0.6rem; border-color:#86efac; background:#f0fdf4;">
                                            ↩ Undo (Mark Unpaid)
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Action row: QR toggle + Pay btn + Mark paid -->
                            <div class="settlement-actions-row">
                                <?php if ($hasUpi): ?>
                                    <button class="btn-qr-toggle btn-sm" onclick="toggleQr(<?= $idx ?>)" title="Show QR for <?= e($st['to']) ?>">
                                        📷 <?= e($st['to']) ?>'s QR
                                    </button>
                                    <a href="<?= e($upiStr) ?>" class="btn btn-pay-sm">📲 Pay Now</a>
                                <?php else: ?>
                                    <span class="no-upi-hint">
                                        <?php if ($isOwner): ?>
                                            <span style="font-size:0.75rem;color:var(--text-dim);">Set UPI for <?= e($st['to']) ?> ↑</span>
                                        <?php else: ?>
                                            <span style="font-size:0.75rem;color:var(--text-dim);">No UPI set for <?= e($st['to']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($isOwner): ?>
                                    <form action="mark_paid" method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="group_id" value="<?= $groupId ?>">
                                        <input type="hidden" name="from_name" value="<?= e($st['from']) ?>">
                                        <input type="hidden" name="to_name" value="<?= e($st['to']) ?>">
                                        <button type="submit" class="btn btn-mark-paid btn-sm"
                                                onclick="return confirm('Mark <?= e($st['from']) ?> as paid to <?= e($st['to']) ?>?');">
                                            ✔ Mark Paid
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Expandable QR Panel -->
                        <?php if ($hasUpi): ?>
                            <div class="settlement-qr-panel" id="qr-panel-<?= $idx ?>" style="display:none;" data-upi="<?= e($upiStr) ?>">
                                <div class="settlement-qr-inner">
                                    <div class="upi-id-label" style="margin-bottom:0.6rem;">
                                        <span>💳</span> <span><?= e($toUpi) ?></span>
                                    </div>
                                    <div id="qr-container-<?= $idx ?>" class="qr-render-box" style="display:flex; justify-content:center; align-items:center; min-height:150px; margin:0.5rem 0;">
                                        <img src="<?= e($qrUrl) ?>"
                                             alt="UPI QR – <?= e($st['to']) ?>"
                                             class="upi-qr-img"
                                             loading="lazy">
                                    </div>
                                    <p class="qr-hint"><?= e($st['from']) ?> → <?= e($st['to']) ?> • <?= formatMoney($st['amount'], $group['currency']) ?></p>
                                    <!-- UPI Payment Apps -->
                                    <div class="upi-apps-section">
                                        <div class="upi-apps-label">⚡ Pay Directly with App</div>
                                        <div class="upi-apps-grid">
                                            <a href="<?= e($gpayUrl) ?>" class="upi-app-btn upi-app-gpay" title="Pay with Google Pay">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/></svg>
                                                <span>GPay</span>
                                            </a>
                                            <a href="<?= e($phonepeUrl) ?>" class="upi-app-btn upi-app-phonepe" title="Pay with PhonePe">
                                                <svg width="18" height="18" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" fill="#5F259F"/><path d="M19.5 8H12a1 1 0 0 0-1 1v2.5h3.2v-1.5h4.8c.8 0 1.5.7 1.5 1.5v1.2c0 .8-.7 1.5-1.5 1.5h-5.2a1 1 0 0 0-1 1v7.8a1 1 0 0 0 2 0v-4.8h3.2l3.4 5.2a1 1 0 0 0 1.7-1.1L21.4 17c1.7-.6 2.8-2.2 2.8-4v-1c0-2.2-1.8-4-4-4z" fill="#FFFFFF"/></svg>
                                                <span>PhonePe</span>
                                            </a>
                                            <a href="<?= e($paytmUrl) ?>" class="upi-app-btn upi-app-paytm" title="Pay with Paytm">
                                                <svg width="26" height="14" viewBox="0 0 46 16" fill="none"><text x="0" y="13" font-size="13" font-weight="900" fill="#ffffff" font-family="sans-serif">Pay</text><text x="24" y="13" font-size="13" font-weight="900" fill="#00B9F5" font-family="sans-serif">tm</text></svg>
                                                <span>Paytm</span>
                                            </a>
                                            <a href="<?= e($bhimUrl) ?>" class="upi-app-btn upi-app-bhim" title="Pay with BHIM">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="4" fill="#005792"/><path d="M6 18L14 6h4L10 18H6z" fill="#00A859"/><path d="M12 18L18 9h2l-6 9h-2z" fill="#FF8300"/></svg>
                                                <span>BHIM</span>
                                            </a>
                                            <a href="<?= e($upiStr) ?>" class="upi-app-btn upi-app-generic" title="Open Default UPI App">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                                <span>Other UPI App</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Individual Breakdown -->
        <div class="settlement-card">
            <h3 class="summary-section-title">👤 Individual Breakdown</h3>
            <div class="breakdown-scroll">
                <table class="balance-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Paid</th>
                            <th>Share</th>
                            <th>Total Cost</th>
                            <th>Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['individual_balances'] as $ib): ?>
                            <tr>
                                <td>
                                    <strong><?= e($ib['name']) ?></strong>
                                    <?php if (!empty($participantUpiMap[$ib['name']])): ?>
                                        <br><span style="font-size:0.7rem;color:#059669;">💳 <?= e($participantUpiMap[$ib['name']]) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatMoney($ib['paid'], $group['currency']) ?></td>
                                <td><?= formatMoney($ib['share'], $group['currency']) ?></td>
                                <td><span class="total-cost-badge"><?= formatMoney($ib['share'], $group['currency']) ?></span></td>
                                <td>
                                    <?php if ($ib['net'] > 0): ?>
                                        <span class="net-positive">+<?= formatMoney($ib['net'], $group['currency']) ?></span>
                                    <?php elseif ($ib['net'] < 0): ?>
                                        <span class="net-negative">-<?= formatMoney(abs($ib['net']), $group['currency']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-dim);"><?= formatMoney(0, $group['currency']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Copy share URL
document.getElementById('copyShareUrlBtn')?.addEventListener('click', function() {
    const input = document.getElementById('shareUrlInput');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        this.textContent = '✅';
        setTimeout(() => this.textContent = '📋', 2000);
    }).catch(() => {
        document.execCommand('copy');
        this.textContent = '✅';
        setTimeout(() => this.textContent = '📋', 2000);
    });
});

// Toggle UPI inline form for a participant
function toggleUpiForm(id) {
    const form = document.getElementById('upi-form-' + id);
    if (!form) return;
    const visible = form.style.display !== 'none';
    document.querySelectorAll('.upi-inline-form').forEach(f => f.style.display = 'none');
    if (!visible) {
        form.style.display = 'block';
        form.querySelector('input[name="upi_id"]')?.focus();
    }
}

// Toggle QR code panel for a settlement (instant offline generation via local qrcode.min.js)
function toggleQr(idx) {
    const panel = document.getElementById('qr-panel-' + idx);
    if (!panel) return;
    const visible = panel.style.display !== 'none';
    document.querySelectorAll('.settlement-qr-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.btn-qr-toggle').forEach(b => b.classList.remove('active'));
    if (!visible) {
        panel.style.display = 'block';
        const btns = document.querySelectorAll('.btn-qr-toggle');
        if (btns[idx]) btns[idx].classList.add('active');

        // Instant offline client-side QR generation (0 network requests)
        const container = document.getElementById('qr-container-' + idx);
        const upiData = panel.getAttribute('data-upi');
        if (container && upiData && typeof QRCode !== 'undefined' && !container.dataset.rendered) {
            container.dataset.rendered = 'true';
            container.innerHTML = '';
            new QRCode(container, {
                text: upiData,
                width: 150,
                height: 150,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
            const qrEl = container.querySelector('img, canvas');
            if (qrEl) {
                qrEl.classList.add('upi-qr-img');
            }
        }

        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Toggle the manual-add form in the participants section
function toggleGroupManualAdd() {
    const el = document.getElementById('groupManualAddFields');
    const btn = document.getElementById('toggleManualAddBtn');
    if (!el) return;
    const showing = el.style.display !== 'none';
    el.style.display = showing ? 'none' : 'block';
    if (!showing) {
        el.querySelector('input[name="name"]')?.focus();
        btn && (btn.textContent = '✖ Cancel');
    } else {
        btn && (btn.textContent = '✏️ Add new person manually');
    }
}
</script>

<script src="js/qrcode.min.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
