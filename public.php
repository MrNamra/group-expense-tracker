<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';
$group = getGroupByToken($token);

if (!$group) {
    require __DIR__ . '/404.php';
    exit;
}

$groupId = (int)$group['id'];
$currentUser = getCurrentUser();
$isOwner = $currentUser && ((int)$group['owner_id'] === (int)$currentUser['id']);

$participants = getGroupParticipants($groupId);
$expenses = getGroupExpenses($groupId);
$paidSettlements = getPaidSettlements($groupId);
$stats = calculateGroupStats($groupId, $paidSettlements);

$pageTitle = $group['name'] . " (Public View)";
$pageDescription = "Public shared view for " . $group['name'] . " expenses, individual balance summaries, and settlement calculations.";

require_once __DIR__ . '/includes/header.php';
?>

<div class="group-header-banner">
    <div class="banner-top">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.4rem; flex-wrap: wrap;">
                <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0;"><?= e($group['name']) ?></h1>
                <span class="badge-owner" style="background: rgba(59, 130, 246, 0.2); color: #2563eb; border-color: rgba(59, 130, 246, 0.4);">Public View Link</span>
            </div>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.5rem;"><?= e($group['description'] ?: 'No description.') ?></p>
            <small style="color: var(--text-dim);">Created by <strong><?= e($group['owner_name']) ?></strong></small>
        </div>

        <?php if ($isOwner): ?>
            <a href="group?id=<?= $groupId ?>" class="btn btn-primary btn-sm">
                ⚙️ Owner Workspace
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="group-content-grid">
    <!-- ── Left Column: Scrollable Expenses ── -->
    <div class="group-left-col">
        <div class="page-header" style="margin-bottom: 1rem;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700;">Group Expenses</h2>
                <p style="font-size: 0.82rem; color: var(--text-muted);"><?= count($expenses) ?> recorded expenses</p>
            </div>
        </div>

        <!-- Scrollable Expenses Box -->
        <div class="expenses-scroll-frame">
            <?php if (empty($expenses)): ?>
                <div class="card" style="text-align: center; padding: 3rem 1.5rem; border: none; box-shadow: none;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🧾</div>
                    <h4>No Expenses Recorded</h4>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">No expenses have been added to this group yet.</p>
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
                                    Shared between: 
                                    <em>
                                        <?= implode(', ', array_map(fn($sd) => e($sd['participant_name']), $exp['split_details'])) ?>
                                    </em>
                                </div>
                            </div>
                        </div>

                        <div class="expense-amount">
                            <?= formatMoney($exp['amount'], $group['currency']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right Column: Summary & Debt Settlements ── -->
    <div>
        <!-- Total Group Stats -->
        <div class="settlement-card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-muted);">
                📊 Total Group Expense
            </h3>
            
            <div style="margin-bottom: 1.25rem;">
                <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-dim); letter-spacing: 0.5px;">Overall Spent</div>
                <div class="summary-amount">
                    <?= formatMoney($stats['total_expense'], $group['currency']) ?>
                </div>
            </div>

            <!-- Settlement Plan: Who pays whom -->
            <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                <span>🤝</span> Who Pays Whom & How Much
            </h4>

            <?php if (empty($stats['settlements'])): ?>
                <div style="font-size: 0.85rem; color: #059669; background: #ecfdf5; padding: 0.75rem; border-radius: var(--radius-sm); border: 2px solid #a7f3d0; text-align: center; font-weight: 700;">
                    ✨ Everyone is settled up!
                </div>
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

                        <!-- Pay and QR actions for public users -->
                        <?php if (!$st['paid'] && $hasUpi): ?>
                            <div class="settlement-actions-row">
                                <button class="btn-qr-toggle btn-sm" onclick="toggleQr(<?= $idx ?>)" title="Show QR for <?= e($st['to']) ?>">
                                    📷 <?= e($st['to']) ?>'s QR
                                </button>
                                <a href="<?= e($upiStr) ?>" class="btn btn-pay-sm">📲 Pay Now</a>
                            </div>

                            <!-- Expandable QR Panel -->
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

        <!-- Individual Balances -->
        <div class="settlement-card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-muted);">
                👤 Individual Expense Summary
            </h3>

            <div class="table-responsive">
                <table class="balance-table">
                    <thead>
                        <tr>
                            <th>Participant</th>
                            <th>Paid</th>
                            <th>Share</th>
                            <th>Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['individual_balances'] as $ib): ?>
                            <tr>
                                <td><strong><?= e($ib['name']) ?></strong></td>
                                <td><?= formatMoney($ib['paid'], $group['currency']) ?></td>
                                <td><?= formatMoney($ib['share'], $group['currency']) ?></td>
                                <td>
                                    <?php if ($ib['net'] > 0): ?>
                                        <span class="net-positive">+<?= formatMoney($ib['net'], $group['currency']) ?></span>
                                    <?php elseif ($ib['net'] < 0): ?>
                                        <span class="net-negative">-<?= formatMoney(abs($ib['net']), $group['currency']) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-dim);"><?= formatMoney(0, $group['currency']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid var(--border-ink); font-weight: 800; background: rgba(0,0,0,0.03);">
                            <td>Total Group Cost</td>
                            <td><?= formatMoney($stats['total_expense'], $group['currency']) ?></td>
                            <td><?= formatMoney($stats['total_expense'], $group['currency']) ?></td>
                            <td>—</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
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

        // Instant offline client-side QR generation
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
</script>

<script src="js/qrcode.min.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
