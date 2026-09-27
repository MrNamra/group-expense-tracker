<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];

$error = '';
$msg = $_GET['msg'] ?? '';

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $upiId = trim($_POST['upi_id'] ?? '');

        if (empty($name)) {
            $error = 'Participant name cannot be empty.';
        } else {
            saveUserContact($userId, $name, $upiId);
            header('Location: contacts?msg=' . urlencode('Friend added successfully!'));
            exit;
        }
    } elseif ($action === 'edit') {
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $upiId = trim($_POST['upi_id'] ?? '');

        if (empty($name)) {
            $error = 'Participant name cannot be empty.';
        } else {
            $ok = updateUserContact($userId, $contactId, $name, $upiId);
            if ($ok) {
                header('Location: contacts?msg=' . urlencode('Friend details updated!'));
                exit;
            } else {
                $error = 'Could not update friend. Another friend with this name may already exist.';
            }
        }
    } elseif ($action === 'delete') {
        $contactId = (int)($_POST['contact_id'] ?? 0);
        if ($contactId > 0) {
            deleteUserContact($userId, $contactId);
            header('Location: contacts?msg=' . urlencode('Friend removed from your saved list.'));
            exit;
        }
    }
}

$contacts = getUserContacts($userId);

$pageTitle = 'Universal Friends & Participants';
$pageDescription = 'Manage your saved friends and global participants with their UPI payment IDs.';

require_once __DIR__ . '/includes/header.php';
?>

<div class="contacts-page-header">
    <div class="contacts-header-info">
        <h1 class="page-title">👥 My Friends & Participants</h1>
        <p class="page-sub">
            Save friends once with their UPI IDs and quickly add them to any group without retyping!
        </p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" id="toggleAddFriendBtn" onclick="toggleAddForm()">
            <span>➕</span> Add New Friend
        </button>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success"><span>✅</span> <?= e($msg) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><span>⚠️</span> <?= e($error) ?></div>
<?php endif; ?>

<!-- Add New Friend Card (Collapsible) -->
<div class="card add-friend-card" id="addFriendCard" style="<?= !empty($error) ? 'display:block;' : 'display:none;' ?>">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="font-size:1.15rem; font-weight:700; margin:0;">✨ Add New Friend / Participant</h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAddForm()">✖ Close</button>
    </div>

    <form action="contacts" method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="add">

        <div class="contact-form-grid">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Friend's Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Alice, Rahul, etc." required value="<?= e($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">UPI ID (Optional)</label>
                <input type="text" name="upi_id" class="form-control" placeholder="e.g. name@okhdfcbank" value="<?= e($_POST['upi_id'] ?? '') ?>">
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1.25rem;">
            <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Friend</button>
        </div>
    </form>
</div>

<!-- Search & Summary Bar -->
<div class="contacts-toolbar">
    <div class="contacts-search-box">
        <input type="text" id="contactSearchInput" class="form-control" placeholder="🔍 Search friends by name or UPI ID..." oninput="filterContacts()">
    </div>
    <div class="contacts-count-badge">
        <span><?= count($contacts) ?></span> <?= count($contacts) === 1 ? 'Friend' : 'Friends' ?> Saved
    </div>
</div>

<!-- Friends List -->
<?php if (empty($contacts)): ?>
    <div class="card" style="text-align:center; padding:3.5rem 1.5rem; margin-top:1rem;">
        <div style="font-size:3rem; margin-bottom:0.75rem;">👥</div>
        <h3 style="font-size:1.3rem; margin-bottom:0.4rem;">No Friends Saved Yet</h3>
        <p style="color:var(--text-muted); font-size:0.92rem; max-width:420px; margin:0 auto 1.5rem auto;">
            When you add friends here, they become globally available so you can add them to any expense group with a single tap!
        </p>
        <button type="button" class="btn btn-primary" onclick="toggleAddForm()">
            <span>➕</span> Add Your First Friend
        </button>
    </div>
<?php else: ?>
    <div class="contacts-cards-grid" id="contactsGrid">
        <?php foreach ($contacts as $c): ?>
            <div class="card contact-record-card" data-name="<?= strtolower(e($c['name'])) ?>" data-upi="<?= strtolower(e($c['upi_id'] ?? '')) ?>">
                <!-- View Mode -->
                <div class="contact-view-mode" id="contact-view-<?= $c['id'] ?>">
                    <div class="contact-card-top">
                        <div class="contact-avatar-lg">
                            <?= strtoupper(mb_substr($c['name'], 0, 1)) ?>
                        </div>

                        <div class="contact-card-info">
                            <h3 class="contact-card-name"><?= e($c['name']) ?></h3>
                            <?php if (!empty($c['upi_id'])): ?>
                                <div class="contact-card-upi">
                                    <span>💳 <?= e($c['upi_id']) ?></span>
                                    <button type="button" class="btn-copy-upi" onclick="copyUpiText('<?= e($c['upi_id']) ?>', this)" title="Copy UPI ID">📋</button>
                                </div>
                            <?php else: ?>
                                <span class="contact-card-no-upi">No UPI ID saved</span>
                            <?php endif; ?>
                        </div>

                        <div class="contact-card-actions">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openEditContact(<?= $c['id'] ?>)" title="Edit">
                                ✏️
                            </button>
                            <form action="contacts" method="POST" style="display:inline;" onsubmit="return confirm('Remove <?= e($c['name']) ?> from your saved friends?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="contact_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">🗑️</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Mode (Inline) -->
                <div class="contact-edit-mode" id="contact-edit-<?= $c['id'] ?>" style="display:none;">
                    <form action="contacts" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="contact_id" value="<?= $c['id'] ?>">

                        <div style="display:flex; flex-direction:column; gap:0.65rem;">
                            <div>
                                <label style="font-size:0.78rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:0.25rem;">Name</label>
                                <input type="text" name="name" class="form-control" value="<?= e($c['name']) ?>" required style="font-size:0.9rem; padding:0.5rem 0.75rem;">
                            </div>
                            <div>
                                <label style="font-size:0.78rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:0.25rem;">UPI ID</label>
                                <input type="text" name="upi_id" class="form-control" value="<?= e($c['upi_id'] ?? '') ?>" placeholder="e.g. user@upi" style="font-size:0.9rem; padding:0.5rem 0.75rem;">
                            </div>
                            <div style="display:flex; justify-content:flex-end; gap:0.4rem; margin-top:0.4rem;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="closeEditContact(<?= $c['id'] ?>)">Cancel</button>
                                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="noSearchResults" class="card" style="display:none; text-align:center; padding:2rem; margin-top:1rem;">
        <p style="color:var(--text-muted); font-size:0.92rem; margin:0;">No friends matched your search.</p>
    </div>
<?php endif; ?>

<script>
function toggleAddForm() {
    const card = document.getElementById('addFriendCard');
    const btn  = document.getElementById('toggleAddFriendBtn');
    if (!card) return;
    const isShowing = card.style.display !== 'none';
    card.style.display = isShowing ? 'none' : 'block';
    if (!isShowing) {
        card.querySelector('input[name="name"]')?.focus();
        btn && (btn.style.display = 'none');
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        btn && (btn.style.display = 'inline-flex');
    }
}

function openEditContact(id) {
    document.querySelectorAll('.contact-edit-mode').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.contact-view-mode').forEach(el => el.style.display = 'block');
    const view = document.getElementById('contact-view-' + id);
    const edit = document.getElementById('contact-edit-' + id);
    if (view && edit) {
        view.style.display = 'none';
        edit.style.display = 'block';
        edit.querySelector('input[name="name"]')?.focus();
    }
}

function closeEditContact(id) {
    const view = document.getElementById('contact-view-' + id);
    const edit = document.getElementById('contact-edit-' + id);
    if (view && edit) {
        view.style.display = 'block';
        edit.style.display = 'none';
    }
}

function filterContacts() {
    const q = document.getElementById('contactSearchInput').value.trim().toLowerCase();
    const cards = document.querySelectorAll('.contact-record-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const name = card.dataset.name || '';
        const upi  = card.dataset.upi || '';
        if (!q || name.includes(q) || upi.includes(q)) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const noMsg = document.getElementById('noSearchResults');
    if (noMsg) {
        noMsg.style.display = (visibleCount === 0 && q) ? 'block' : 'none';
    }
}

function copyUpiText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const old = btn.textContent;
        btn.textContent = '✅';
        setTimeout(() => btn.textContent = old, 1800);
    }).catch(() => {
        btn.textContent = '✅';
        setTimeout(() => btn.textContent = '📋', 1800);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
