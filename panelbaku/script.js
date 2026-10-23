document.addEventListener('DOMContentLoaded', function () {
    // --- Dark / Light tema ---
    const themeToggle = document.getElementById('themeToggle');

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        if (themeToggle) themeToggle.textContent = theme === 'dark' ? '☀️' : '🌙';
        try { localStorage.setItem('panelbaku_theme', theme); } catch (e) {}
    }

    applyTheme(document.documentElement.getAttribute('data-theme') || 'light');

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    const dataEl = document.getElementById('categoriesData');
    // Servis xətası olanda (error-box) interfeys elementləri render olunmur
    if (!dataEl || !document.getElementById('categoryTrigger')) return;
    let categories = JSON.parse(dataEl.textContent || '[]');

    const selectTrigger = document.getElementById('categoryTrigger');
    const selectLabel = document.getElementById('categoryTriggerLabel');
    const dropdown = document.getElementById('categoryDropdown');
    const searchInput = document.getElementById('categorySearch');
    const optionsList = document.getElementById('categoryOptions');
    const messageBox = document.getElementById('messageBox');
    const shareBtn = document.getElementById('shareBtn');
    const copyBtn = document.getElementById('copyBtn');
    const statusBox = document.getElementById('statusBox');
    const historyList = document.getElementById('historyList');
    const clearHistoryBtn = document.getElementById('clearHistoryBtn');

    let selectedCategory = null;

    // Dropdown-u <body>-nin sonuna köçürürük ki, heç bir "backdrop-filter"
    // olan kart onu öz içində "həbs" edə bilməsin (stacking context problemi).
    document.body.appendChild(dropdown);

    function positionDropdown() {
        const rect = selectTrigger.getBoundingClientRect();
        dropdown.style.left = rect.left + 'px';
        dropdown.style.top = (rect.bottom + 8) + 'px';
        dropdown.style.width = rect.width + 'px';
    }

    function iconFor(name) {
        const n = name.toLowerCase();
        if (n.includes('instagram')) return '📸';
        if (n.includes('facebook')) return '📘';
        if (n.includes('tiktok')) return '🎵';
        if (n.includes('youtube')) return '▶️';
        if (n.includes('telegram')) return '✈️';
        if (n.includes('twitter') || n.includes(' x ') || n.startsWith('x ')) return '🐦';
        if (n.includes('discord')) return '🎮';
        if (n.includes('pinterest')) return '📌';
        if (n.includes('snapchat')) return '👻';
        if (n.includes('twitch')) return '🎥';
        if (n.includes('linkedin')) return '💼';
        if (n.includes('whatsapp')) return '💬';
        if (n.includes('kick')) return '🥊';
        return '⭐';
    }

    function renderOptions(filter) {
        const q = (filter || '').trim().toLowerCase();
        optionsList.innerHTML = '';

        const filtered = categories.filter(c => c.name.toLowerCase().includes(q));

        if (filtered.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'option-empty';
            empty.textContent = 'Nəticə tapılmadı';
            optionsList.appendChild(empty);
            return;
        }

        filtered.forEach(cat => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'option-item';
            item.innerHTML = `
                <span class="option-icon">${iconFor(cat.name)}</span>
                <span class="option-name">${escapeHtml(cat.name)}</span>
                <span class="option-count">${cat.services.length}</span>
            `;
            item.addEventListener('click', () => selectCategory(cat));
            optionsList.appendChild(item);
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatRate(rate) {
        const n = parseFloat(String(rate).replace(',', '.'));
        if (isNaN(n)) return rate;
        return n.toFixed(2);
    }

    function buildTemplate(cat) {
        const lines = [];
        lines.push(`${iconFor(cat.name)} ${cat.name}`);
        lines.push('');

        cat.services.forEach(s => {
            lines.push(`🆔 ${s.id} - ${s.name} -- ${formatRate(s.rate)}`);
            lines.push('');
        });

        lines.push('50 azn və yuxarı Balans artırmalarda + %5 Bonus 🎁');
        lines.push('');
        lines.push('🔗 https://panelbaku.com');
        return lines.join('\n');
    }

    function selectCategory(cat) {
        selectedCategory = cat;
        selectLabel.textContent = cat.name;
        selectLabel.classList.add('has-value');
        closeDropdown();

        // Kateqoriya DƏYİŞƏNDƏ mesaj yenidən avtomatik yaradılır
        // (istifadəçinin əvvəlki redaktəsi üstündən silinir - bu, yeni seçimdir)
        messageBox.value = buildTemplate(cat);
        updateCharCount();
        shareBtn.disabled = false;
        copyBtn.disabled = false;
        setStatus('', null);
    }

    function openDropdown() {
        positionDropdown();
        dropdown.classList.add('open');
        selectTrigger.classList.add('open');
        searchInput.value = '';
        renderOptions('');
        setTimeout(() => searchInput.focus(), 50);
    }

    function closeDropdown() {
        dropdown.classList.remove('open');
        selectTrigger.classList.remove('open');
    }

    // Səhifənin özü skroll olunanda açıq dropdown-u bağlayırıq,
    // AMMA dropdown-un öz daxili siyahısını skroll etməyə mane olmuruq.
    window.addEventListener('scroll', (e) => {
        if (!dropdown.classList.contains('open')) return;
        if (document.activeElement === searchInput) return; // mobil klaviatura səbəbli skrollda bağlama
        if (dropdown.contains(e.target)) return;
        closeDropdown();
    }, true);

    window.addEventListener('resize', () => {
        if (dropdown.classList.contains('open')) positionDropdown();
    });

    selectTrigger.addEventListener('click', () => {
        if (dropdown.classList.contains('open')) {
            closeDropdown();
        } else {
            openDropdown();
        }
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && !selectTrigger.contains(e.target)) {
            closeDropdown();
        }
    });

    searchInput.addEventListener('input', () => renderOptions(searchInput.value));

    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(messageBox.value);
            setStatus('Mətn kopyalandı ✅', 'ok');
        } catch (e) {
            setStatus('Kopyalama alınmadı, əl ilə seç.', 'error');
        }
    });

    shareBtn.addEventListener('click', async () => {
        // Paylaşılan mətn HƏMİŞƏ textarea-nın canlı (istifadəçi redaktə etmiş ola bilər) dəyəridir
        const currentText = messageBox.value;
        if (!currentText.trim()) return;

        shareBtn.disabled = true;
        shareBtn.textContent = 'Göndərilir...';
        setStatus('', null);

        try {
            const res = await fetch('share.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    text: currentText,
                    category: selectedCategory ? selectedCategory.name : '',
                }),
            });
            const data = await res.json();

            if (data.ok) {
                setStatus('Telegram kanalına göndərildi ✅', 'ok');
                if (data.entry) {
                    prependHistoryItem(data.entry);
                }
            } else {
                setStatus(data.error || 'Naməlum xəta baş verdi.', 'error');
            }
        } catch (e) {
            setStatus('Şəbəkə xətası: mesaj göndərilmədi.', 'error');
        } finally {
            shareBtn.disabled = false;
            shareBtn.textContent = '📤 Paylaş';
        }
    });

    function prependHistoryItem(entry) {
        const emptyEl = document.getElementById('historyEmpty');
        if (emptyEl) emptyEl.remove();

        const item = document.createElement('div');
        item.className = 'history-item';
        item.dataset.id = entry.id || '';
        item.innerHTML = `
            <div class="history-item-top">
                <span class="history-cat">${escapeHtml(entry.category || '')}</span>
                <div class="history-item-right">
                    <span class="history-time">${escapeHtml(entry.time || '')}</span>
                    <button type="button" class="history-delete-btn" title="Sil">🗑</button>
                </div>
            </div>
            <div class="history-preview">${escapeHtml(entry.preview || '')}…</div>
        `;
        historyList.prepend(item);
    }

    async function deleteHistoryItem(id, itemEl) {
        try {
            const res = await fetch('history_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id }),
            });
            const data = await res.json();
            if (data.ok) {
                itemEl.remove();
                if (!historyList.querySelector('.history-item')) {
                    showHistoryEmpty();
                }
            } else {
                alert(data.error || 'Silinmə uğursuz oldu.');
            }
        } catch (e) {
            alert('Şəbəkə xətası: silinmədi.');
        }
    }

    function showHistoryEmpty() {
        if (document.getElementById('historyEmpty')) return;
        const empty = document.createElement('div');
        empty.className = 'history-empty';
        empty.id = 'historyEmpty';
        empty.textContent = 'Hələ paylaşım yoxdur';
        historyList.appendChild(empty);
    }

    historyList.addEventListener('click', (e) => {
        const btn = e.target.closest('.history-delete-btn');
        if (!btn) return;
        const itemEl = btn.closest('.history-item');
        const id = itemEl ? itemEl.dataset.id : '';
        if (!id) return;
        deleteHistoryItem(id, itemEl);
    });

    if (clearHistoryBtn) {
        clearHistoryBtn.addEventListener('click', async () => {
            if (!confirm('Bütün tarixçəni silmək istədiyinə əminsən?')) return;
            try {
                const res = await fetch('history_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear' }),
                });
                const data = await res.json();
                if (data.ok) {
                    historyList.innerHTML = '';
                    showHistoryEmpty();
                } else {
                    alert(data.error || 'Təmizləmə uğursuz oldu.');
                }
            } catch (e) {
                alert('Şəbəkə xətası: təmizlənmədi.');
            }
        });
    }

    const charCount = document.getElementById('charCount');
    function updateCharCount() {
        if (!charCount) return;
        const n = messageBox.value.length;
        charCount.textContent = n + ' / 4096';
        charCount.classList.toggle('over', n > 4096);
    }
    messageBox.addEventListener('input', updateCharCount);

    function setStatus(text, type) {
        statusBox.textContent = text;
        statusBox.className = 'status-box' + (type ? ' ' + type : '');
    }

    // --- Servisləri dinamik yeniləmə ---
    const refreshBtn = document.getElementById('refreshBtn');
    const statCategories = document.getElementById('statCategories');
    const statServices = document.getElementById('statServices');
    const AUTO_REFRESH_MS = 5 * 60 * 1000;

    async function refreshServices(force) {
        if (refreshBtn) refreshBtn.classList.add('spinning');
        try {
            const res = await fetch('services.php' + (force ? '?refresh=1' : ''), { cache: 'no-store' });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Naməlum xəta');

            categories = data.categories;
            if (statCategories) statCategories.textContent = categories.length;
            if (statServices) {
                statServices.textContent = categories.reduce((n, c) => n + c.services.length, 0);
            }

            // Seçili kateqoriya hələ varsa: mesajı əl ilə dəyişməyibsə yenilə, dəyişibsə toxunma
            if (selectedCategory) {
                const fresh = categories.find(c => c.name === selectedCategory.name);
                if (fresh) {
                    if (messageBox.value === buildTemplate(selectedCategory)) {
                        messageBox.value = buildTemplate(fresh);
                        updateCharCount();
                    }
                    selectedCategory = fresh;
                }
            }
            if (dropdown.classList.contains('open')) renderOptions(searchInput.value);
            if (force) setStatus('Servislər yeniləndi ✅', 'ok');
        } catch (e) {
            if (force) setStatus('Yeniləmə alınmadı: ' + e.message, 'error');
        } finally {
            if (refreshBtn) refreshBtn.classList.remove('spinning');
        }
    }

    if (refreshBtn) refreshBtn.addEventListener('click', () => refreshServices(true));
    setInterval(() => { if (!document.hidden) refreshServices(false); }, AUTO_REFRESH_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshServices(false); });

    renderOptions('');
});
