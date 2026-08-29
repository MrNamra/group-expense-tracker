document.addEventListener('DOMContentLoaded', function () {
    // Copy Shareable URL
    const copyBtn = document.getElementById('copyShareUrlBtn');
    const shareInput = document.getElementById('shareUrlInput');

    if (copyBtn && shareInput) {
        copyBtn.addEventListener('click', function () {
            shareInput.select();
            shareInput.setSelectionRange(0, 99999); // For mobile devices
            navigator.clipboard.writeText(shareInput.value).then(() => {
                const originalText = copyBtn.innerText;
                copyBtn.innerText = 'Copied! ✓';
                copyBtn.classList.add('btn-primary');
                setTimeout(() => {
                    copyBtn.innerText = originalText;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy token: ', err);
            });
        });
    }

    // Select All / Deselect All Participants Checkboxes
    const selectAllBtn = document.getElementById('selectAllParticipants');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const checkboxes = document.querySelectorAll('.split-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
            selectAllBtn.innerText = allChecked ? 'Select All' : 'Deselect All';
        });
    }

    // Dynamic Participant Add row in Create Group Form
    const addParticipantBtn = document.getElementById('addMoreParticipantBtn');
    const participantContainer = document.getElementById('participantInputsContainer');

    if (addParticipantBtn && participantContainer) {
        let pCount = participantContainer.querySelectorAll('.form-group').length + 1;
        
        addParticipantBtn.addEventListener('click', function () {
            const div = document.createElement('div');
            div.className = 'form-group';
            div.style.display = 'flex';
            div.style.gap = '0.5rem';
            div.innerHTML = `
                <input type="text" name="participants[]" class="form-control" placeholder="Participant #${pCount} Name" required>
                <button type="button" class="btn btn-danger btn-sm remove-participant-btn">&times;</button>
            `;
            participantContainer.appendChild(div);
            pCount++;
        });

        participantContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-participant-btn')) {
                e.target.parentElement.remove();
            }
        });
    }

    // Convert Select dropdowns (e.g. payer_id) to Searchable Selects
    initSearchableSelects();
});

function initSearchableSelects() {
    const selects = document.querySelectorAll('select[name="payer_id"], select.searchable-select');

    selects.forEach(select => {
        if (select.dataset.searchableInit) return;
        select.dataset.searchableInit = "true";
        select.style.display = "none"; // Hide raw native select

        const wrapper = document.createElement('div');
        wrapper.className = 'searchable-select-wrapper';

        // Currently selected option text
        const selectedOpt = select.options[select.selectedIndex];
        const selectedText = selectedOpt ? selectedOpt.text : '-- Select Participant --';

        const trigger = document.createElement('div');
        trigger.className = 'searchable-select-trigger';
        trigger.innerHTML = `<span>${escapeHtml(selectedText)}</span>`;

        const dropdown = document.createElement('div');
        dropdown.className = 'searchable-select-dropdown';

        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'searchable-select-input';
        searchInput.placeholder = '🔍 Type to search person...';

        const optionsContainer = document.createElement('div');
        optionsContainer.className = 'searchable-select-options';

        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'searchable-select-empty';
        emptyMsg.innerText = 'No matching participant found';

        // Populate options
        Array.from(select.options).forEach((opt, idx) => {
            const optDiv = document.createElement('div');
            optDiv.className = 'searchable-select-option' + (opt.selected ? ' selected' : '');
            optDiv.innerText = opt.text;
            optDiv.dataset.value = opt.value;

            optDiv.addEventListener('click', function () {
                select.value = opt.value;
                select.dispatchEvent(new Event('change'));
                trigger.querySelector('span').innerText = opt.text;

                dropdown.querySelectorAll('.searchable-select-option').forEach(o => o.classList.remove('selected'));
                optDiv.classList.add('selected');
                wrapper.classList.remove('open');
            });

            optionsContainer.appendChild(optDiv);
        });

        optionsContainer.appendChild(emptyMsg);
        dropdown.appendChild(searchInput);
        dropdown.appendChild(optionsContainer);
        wrapper.appendChild(trigger);
        wrapper.appendChild(dropdown);

        select.parentNode.insertBefore(wrapper, select.nextSibling);

        // Toggle dropdown open/close
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            // Close other open searchable selects
            document.querySelectorAll('.searchable-select-wrapper.open').forEach(w => {
                if (w !== wrapper) w.classList.remove('open');
            });

            const isOpen = wrapper.classList.toggle('open');
            if (isOpen) {
                searchInput.value = '';
                filterOptions('');
                setTimeout(() => searchInput.focus(), 50);
            }
        });

        // Filter options on input search
        searchInput.addEventListener('input', function () {
            filterOptions(searchInput.value.trim().toLowerCase());
        });

        function filterOptions(query) {
            let visibleCount = 0;
            const optElements = optionsContainer.querySelectorAll('.searchable-select-option');

            optElements.forEach(optEl => {
                const text = optEl.innerText.toLowerCase();
                if (text.includes(query)) {
                    optEl.classList.remove('hidden');
                    visibleCount++;
                } else {
                    optEl.classList.add('hidden');
                }
            });

            emptyMsg.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove('open');
            }
        });
    });
}

function escapeHtml(str) {
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
