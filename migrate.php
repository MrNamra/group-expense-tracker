<?php
/**
 * SplitWise PRO - Web & CLI Database Migration Runner
 * 
 * Run from browser: https://yourdomain.com/migrate.php
 * Run from CLI:     php migrate.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$isCli = (php_sapi_name() === 'cli');

// ── Security Check ────────────────────────────────────────────────────────────
// In production, migrations can be run by:
// 1. Any logged-in user
// 2. OR by passing ?key=YOUR_SECRET (matches MIGRATION_KEY below)
// 3. OR via CLI terminal
define('MIGRATION_SECRET_KEY', 'splitwise_admin_migrate');

$hasAccess = false;
if ($isCli || isLoggedIn()) {
    $hasAccess = true;
} elseif (isset($_GET['key']) && $_GET['key'] === MIGRATION_SECRET_KEY) {
    $hasAccess = true;
} elseif (isset($_POST['migration_key']) && $_POST['migration_key'] === MIGRATION_SECRET_KEY) {
    $hasAccess = true;
}

$pdo = getDBConnection();
$driver = DB_DRIVER;

// Handle Run Action
$runLogs = null;
if ($hasAccess && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['run']) || $isCli)) {
    $runLogs = checkAndApplyMigrations($pdo, $driver);
}

// ── CLI Output ────────────────────────────────────────────────────────────────
if ($isCli) {
    echo "========================================\n";
    echo " SplitWise PRO - Database Migration CLI \n";
    echo "========================================\n";
    echo "Driver: {$driver}\n\n";
    if ($runLogs) {
        foreach ($runLogs as $entry) {
            echo " [✓] {$entry}\n";
        }
    }
    echo "\nAll migrations completed successfully!\n";
    exit(0);
}

// ── Web UI Output ─────────────────────────────────────────────────────────────
$pageTitle = "Database Migrations";
$pageDescription = "Run pending database schema migrations for SplitWise PRO.";

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 720px; margin: 2rem auto;">
    <div class="card" style="padding: 2rem;">
        <div style="display:flex; align-items:center; gap:0.85rem; margin-bottom:1.5rem; flex-wrap:wrap;">
            <div style="font-size:2.4rem; background:var(--bg-card-alt); border:2px solid var(--border-ink); border-radius:var(--radius-md); width:58px; height:58px; display:flex; align-items:center; justify-content:center; box-shadow:2px 2px 0px var(--border-ink);">
                🛠️
            </div>
            <div>
                <h1 style="font-size:1.75rem; font-weight:800; margin:0;">Database Migration Runner</h1>
                <p style="color:var(--text-muted); font-size:0.88rem; margin:0.25rem 0 0 0;">
                    Safely inspect and apply schema updates, missing columns, and new tables without data loss.
                </p>
            </div>
        </div>

        <!-- Database Connection Badge -->
        <div style="display:flex; gap:0.6rem; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap;">
            <span style="font-size:0.82rem; font-weight:700; background:#f1f5f9; border:1.5px solid var(--border-ink); padding:0.35rem 0.75rem; border-radius:var(--radius-full);">
                🔌 Database Driver: <strong style="color:var(--color-primary);"><?= strtoupper($driver) ?></strong>
            </span>
            <span style="font-size:0.82rem; font-weight:700; background:#ecfdf5; color:#059669; border:1.5px solid #a7f3d0; padding:0.35rem 0.75rem; border-radius:var(--radius-full);">
                🟢 Status: Connected
            </span>
        </div>

        <?php if (!$hasAccess): ?>
            <!-- Access Key Form (When not logged in) -->
            <div class="card" style="background:#fffdf7; border:2px dashed var(--border-ink); padding:1.5rem; margin-top:1rem;">
                <h3 style="font-size:1.1rem; margin-bottom:0.5rem;">🔒 Migration Access Required</h3>
                <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:1rem;">
                    Please <a href="login" style="color:var(--color-primary); font-weight:700; text-decoration:underline;">login as a user</a>, or enter the migration secret key to proceed.
                </p>
                <form action="migrate.php" method="POST" style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                    <input type="password" name="migration_key" class="form-control" placeholder="Enter Migration Secret Key" style="flex:1; min-width:200px;" required>
                    <button type="submit" class="btn btn-primary">Unlock & Run</button>
                </form>
                <small style="color:var(--text-dim); display:block; margin-top:0.6rem;">
                    Default key: <code>splitwise_admin_migrate</code> (configurable in <code>migrate.php</code>)
                </small>
            </div>
        <?php else: ?>

            <?php if ($runLogs !== null): ?>
                <!-- Execution Results -->
                <div class="alert alert-success" style="margin-bottom:1.5rem;">
                    <div>
                        <strong>✅ Migration Check Complete!</strong>
                        <p style="margin:0.25rem 0 0 0; font-size:0.85rem;">All tables and columns are up to date and ready for production.</p>
                    </div>
                </div>

                <div style="background:#ffffff; border:2px solid var(--border-ink); border-radius:var(--radius-md); padding:1.25rem; margin-bottom:1.5rem; box-shadow:2px 2px 0px var(--border-ink);">
                    <h3 style="font-size:0.95rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.85rem; color:var(--text-muted);">
                        📋 Execution Log
                    </h3>
                    <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.6rem;">
                        <?php foreach ($runLogs as $entry): ?>
                            <li style="display:flex; align-items:flex-start; gap:0.55rem; font-size:0.88rem; font-weight:600; color:var(--text-main);">
                                <span style="color:#16a34a; font-weight:800;">✓</span>
                                <span><?= e($entry) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Schema Checklist -->
            <div style="background:#f8fafc; border:2px solid var(--border-ink); border-radius:var(--radius-md); padding:1.25rem; margin-bottom:1.5rem;">
                <h3 style="font-size:0.95rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.85rem; color:var(--text-muted);">
                    📦 Checked Migration Modules
                </h3>
                <div style="display:flex; flex-direction:column; gap:0.65rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1.5px solid var(--border-ink); padding:0.6rem 0.85rem; border-radius:var(--radius-sm);">
                        <div>
                            <strong>1. Participants UPI Support</strong>
                            <div style="font-size:0.78rem; color:var(--text-muted);">Column <code>participants.upi_id</code> for individual QR payment codes</div>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:#16a34a; background:#dcfce7; padding:0.2rem 0.5rem; border-radius:4px; border:1px solid #86efac;">Active</span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1.5px solid var(--border-ink); padding:0.6rem 0.85rem; border-radius:var(--radius-sm);">
                        <div>
                            <strong>2. Group Owner UPI Support</strong>
                            <div style="font-size:0.78rem; color:var(--text-muted);">Column <code>groups.upi_id</code> for owner fallback payments</div>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:#16a34a; background:#dcfce7; padding:0.2rem 0.5rem; border-radius:4px; border:1px solid #86efac;">Active</span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1.5px solid var(--border-ink); padding:0.6rem 0.85rem; border-radius:var(--radius-sm);">
                        <div>
                            <strong>3. Paid Debt Settlements Tracking</strong>
                            <div style="font-size:0.78rem; color:var(--text-muted);">Table <code>paid_settlements</code> for marking settled debts as 0.00</div>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:#16a34a; background:#dcfce7; padding:0.2rem 0.5rem; border-radius:4px; border:1px solid #86efac;">Active</span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1.5px solid var(--border-ink); padding:0.6rem 0.85rem; border-radius:var(--radius-sm);">
                        <div>
                            <strong>4. Universal Contacts Book</strong>
                            <div style="font-size:0.78rem; color:var(--text-muted);">Table <code>user_contacts</code> for global saved friends across groups</div>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:#16a34a; background:#dcfce7; padding:0.2rem 0.5rem; border-radius:4px; border:1px solid #86efac;">Active</span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1.5px solid var(--border-ink); padding:0.6rem 0.85rem; border-radius:var(--radius-sm);">
                        <div>
                            <strong>5. Auto-populate Universal Contacts</strong>
                            <div style="font-size:0.78rem; color:var(--text-muted);">Imports existing participants into user contact books automatically</div>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:#16a34a; background:#dcfce7; padding:0.2rem 0.5rem; border-radius:4px; border:1px solid #86efac;">Active</span>
                    </div>
                </div>
            </div>

            <!-- Run Button Form -->
            <form action="migrate.php" method="POST" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                <input type="hidden" name="migration_key" value="<?= MIGRATION_SECRET_KEY ?>">
                <a href="dashboard" class="btn btn-secondary">
                    ← Back to Dashboard
                </a>
                <button type="submit" class="btn btn-primary" style="font-size:1rem; padding:0.75rem 1.5rem;">
                    <span>🚀</span> Run / Re-run Migrations
                </button>
            </form>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
