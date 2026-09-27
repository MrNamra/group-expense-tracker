<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$contacts    = getUserContacts($currentUser['id']); // saved universal friends
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name        = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $participants = $_POST['participants'] ?? [];   // array of 'name||upi_id' packed strings
        $currency    = $_POST['currency'] ?? '₹';

        if (empty(trim($name))) {
            $error = 'Group name is required.';
        } else {
            try {
                $groupId = createGroup($currentUser['id'], $name, $description, $participants, $currency);
                header("Location: group?id=" . $groupId . "&msg=" . urlencode("Group created! You can now add expenses."));
                exit;
            } catch (Exception $e) {
                $error = 'Failed to create group: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Create Group";
$pageDescription = "Create a new expense group and add your friends from your contact book.";
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container" style="max-width: 600px;">
    <div class="card">
        <h2 class="card-title">✨ Create New Group</h2>
        <p class="card-subtitle">Set up an expense group and pick friends from your contact book.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><span>⚠️</span> <?= e($error) ?></div>
        <?php endif; ?>

        <form action="create_group" method="POST" id="createGroupForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- Group name + currency -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Group Name *</label>
                    <input type="text" name="name" class="form-control"
                           placeholder="e.g. Goa Trip, Flat Expenses"
                           value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Currency *</label>
                    <select name="currency" class="form-control" required>
                        <?php foreach (SUPPORTED_CURRENCIES as $symbol => $label): ?>
                            <option value="<?= e($symbol) ?>" <?= (($_POST['currency'] ?? '₹') === $symbol) ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description (Optional)</label>
                <textarea name="description" class="form-control" rows="2"
                          placeholder="What is this group for?"><?= e($_POST['description'] ?? '') ?></textarea>
            </div>

            <hr style="border:0; border-top: 2px dashed var(--border-ink); margin: 1.5rem 0;">

            <!-- ── Participants Section ── -->
            <div class="form-group">
                <label class="form-label" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                    <span>👥 Add Participants</span>
                    <small style="color:var(--text-dim);">You (<?= e($currentUser['username']) ?>) are added automatically</small>
                </label>

                <!-- Contacts Quick-Pick (shown if user has contacts) -->
                <?php if (!empty($contacts)): ?>
                    <div class="contacts-picker-panel">
                        <div class="contacts-picker-label">
                            📋 Pick from your contacts — tap to add:
                        </div>
                        <div class="contacts-chip-list" id="contactsChipList">
                            <?php foreach ($contacts as $c): ?>
                                <button type="button"
                                        class="contact-chip"
                                        data-name="<?= e($c['name']) ?>"
                                        data-upi="<?= e($c['upi_id'] ?? '') ?>"
                                        onclick="addContactToGroup(this)">
                                    <span class="contact-chip-avatar"><?= strtoupper(mb_substr($c['name'], 0, 1)) ?></span>
                                    <span class="contact-chip-name"><?= e($c['name']) ?></span>
                                    <?php if (!empty($c['upi_id'])): ?>
                                        <span class="contact-chip-upi">💳</span>
                                    <?php endif; ?>
                                    <span class="contact-chip-add">+</span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Selected participants (hidden inputs + visible chips) -->
                <div class="selected-participants-box" id="selectedParticipantsBox" style="<?= !empty($contacts) ? '' : 'display:none;' ?>">
                    <div class="selected-participants-label">Selected participants:</div>
                    <div class="selected-chips-row" id="selectedChipsRow"></div>
                </div>

                <!-- Hidden inputs container (submitted with form) -->
                <div id="hiddenInputsContainer"></div>

                <!-- Manual add row -->
                <div class="manual-add-row" id="manualAddSection">
                    <?php if (!empty($contacts)): ?>
                        <div class="manual-add-toggle">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleManualAdd()">
                                ✏️ Add someone new
                            </button>
                        </div>
                    <?php endif; ?>
                    <div id="manualAddFields" style="<?= empty($contacts) ? '' : 'display:none;' ?>">
                        <div class="manual-add-fields-grid" id="manualParticipantInputs">
                            <div class="manual-participant-row">
                                <input type="text" class="form-control manual-name-input"
                                       placeholder="Name (e.g. Alice)">
                                <input type="text" class="form-control manual-upi-input"
                                       placeholder="UPI ID (optional)">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="addManualParticipant(this)">➕ Add</button>
                            </div>
                        </div>
                        <?php if (empty($contacts)): ?>
                            <button type="button" class="btn btn-secondary btn-sm" style="margin-top:0.5rem;" onclick="addManualRow()">
                                ➕ Add another person
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:1rem; margin-top:2rem;">
                <a href="dashboard" class="btn btn-secondary" style="flex:1;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex:2;">
                    <span>✨</span> Create Group
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Track selected participants to avoid duplicates
const selected = new Set();

function addContactToGroup(btn) {
    const name = btn.dataset.name;
    const upi  = btn.dataset.upi || '';

    if (selected.has(name)) {
        // Remove if already selected
        removeParticipant(name);
        btn.classList.remove('contact-chip-selected');
        btn.querySelector('.contact-chip-add').textContent = '+';
        return;
    }

    selected.add(name);
    btn.classList.add('contact-chip-selected');
    btn.querySelector('.contact-chip-add').textContent = '✓';

    // Add hidden input (packed name||upi)
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'participants[]';
    input.value = upi ? `${name}||${upi}` : name;
    input.id = `hidden-${name}`;
    document.getElementById('hiddenInputsContainer').appendChild(input);

    // Show selected chip
    const box = document.getElementById('selectedParticipantsBox');
    box.style.display = 'block';
    const row = document.getElementById('selectedChipsRow');
    const chip = document.createElement('span');
    chip.className = 'selected-chip';
    chip.id = `chip-${name}`;
    chip.innerHTML = `<span>${name}</span>${upi ? '<span class="chip-upi">💳</span>' : ''}<button type="button" onclick="removeParticipantAndBtn('${name.replace(/'/g, "\\'")}')">×</button>`;
    row.appendChild(chip);
}

function removeParticipant(name) {
    selected.delete(name);
    document.getElementById(`hidden-${name}`)?.remove();
    document.getElementById(`chip-${name}`)?.remove();
    const box = document.getElementById('selectedParticipantsBox');
    if (selected.size === 0) box.style.display = 'none';
}

function removeParticipantAndBtn(name) {
    removeParticipant(name);
    // Also uncheck contact chip button
    document.querySelectorAll('.contact-chip').forEach(btn => {
        if (btn.dataset.name === name) {
            btn.classList.remove('contact-chip-selected');
            btn.querySelector('.contact-chip-add').textContent = '+';
        }
    });
}

// Manual add (when user types a new name not in contacts)
function addManualParticipant(btn) {
    const row  = btn.closest('.manual-participant-row');
    const name = row.querySelector('.manual-name-input').value.trim();
    const upi  = row.querySelector('.manual-upi-input').value.trim();

    if (!name) { row.querySelector('.manual-name-input').focus(); return; }
    if (selected.has(name)) {
        alert(`"${name}" is already added.`);
        return;
    }

    selected.add(name);

    // Hidden input
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'participants[]';
    input.value = upi ? `${name}||${upi}` : name;
    input.id = `hidden-${name}`;
    document.getElementById('hiddenInputsContainer').appendChild(input);

    // Show selected chip
    const box = document.getElementById('selectedParticipantsBox');
    box.style.display = 'block';
    const chipRow = document.getElementById('selectedChipsRow');
    const chip = document.createElement('span');
    chip.className = 'selected-chip';
    chip.id = `chip-${name}`;
    chip.innerHTML = `<span>${name}</span>${upi ? '<span class="chip-upi">💳</span>' : ''}<button type="button" onclick="removeParticipantAndBtn('${name.replace(/'/g, "\\'")}')">×</button>`;
    chipRow.appendChild(chip);

    // Clear the row
    row.querySelector('.manual-name-input').value = '';
    row.querySelector('.manual-upi-input').value = '';
    row.querySelector('.manual-name-input').focus();
}

function addManualRow() {
    const container = document.getElementById('manualParticipantInputs');
    const row = document.createElement('div');
    row.className = 'manual-participant-row';
    row.style.marginTop = '0.5rem';
    row.innerHTML = `
        <input type="text" class="form-control manual-name-input" placeholder="Name">
        <input type="text" class="form-control manual-upi-input" placeholder="UPI ID (optional)">
        <button type="button" class="btn btn-secondary btn-sm" onclick="addManualParticipant(this)">➕ Add</button>
    `;
    container.appendChild(row);
    row.querySelector('.manual-name-input').focus();
}

function toggleManualAdd() {
    const el = document.getElementById('manualAddFields');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// Enter key on manual name input triggers add
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.classList.contains('manual-name-input')) {
        e.preventDefault();
        e.target.closest('.manual-participant-row')?.querySelector('button[onclick*="addManualParticipant"]')?.click();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
