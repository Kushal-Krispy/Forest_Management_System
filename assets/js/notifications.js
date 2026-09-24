/**
 * Real-time notification center (AJAX polling fallback)
 */
(function () {
    const pollInterval = 30000;
    let timer = null;

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    async function fetchCount() {
        try {
            const res = await fetch(BASE_URL + '/api/notifications.php?action=count');
            const data = await res.json();
            const badge = document.getElementById('notificationCount');
            if (badge) {
                if (data.count > 0) {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        } catch (e) { /* silent */ }
    }

    async function fetchList() {
        const list = document.getElementById('notificationList');
        const markAllBtn = document.getElementById('markAllRead');
        if (!list) return;
        try {
            const res = await fetch(BASE_URL + '/api/notifications.php?action=list');
            const data = await res.json();
            if (!data.notifications || data.notifications.length === 0) {
                list.innerHTML = '<div class="notification-empty">No notifications</div>';
                if (markAllBtn) markAllBtn.style.display = 'none';
                return;
            }
            list.innerHTML = data.notifications.map(n => `
                <div class="notification-item ${n.is_read == 0 ? 'unread' : ''}" data-id="${n.notification_id}">
                    <div class="notification-type type-${n.type}"><i class="fas fa-bell"></i></div>
                    <div class="notification-body">
                        <strong>${escapeHtml(n.title)}</strong>
                        <p>${escapeHtml(n.message)}</p>
                        <small>${n.created_at}</small>
                    </div>
                </div>
            `).join('');
            list.querySelectorAll('.notification-item.unread').forEach(el => {
                el.addEventListener('click', () => markRead(el.dataset.id));
            });
            if (markAllBtn) markAllBtn.style.display = 'inline-block';
        } catch (e) {
            list.innerHTML = '<div class="notification-empty">Failed to load</div>';
            if (markAllBtn) markAllBtn.style.display = 'none';
        }
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    async function markRead(id) {
        const fd = new FormData();
        fd.append('id', id);
        await fetch(BASE_URL + '/api/notifications.php?action=mark_read', { method: 'POST', body: fd });
        fetchCount();
        fetchList();
    }

    document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
        e.preventDefault();
        await fetch(BASE_URL + '/api/notifications.php?action=mark_all_read', { method: 'POST' });
        fetchCount();
        fetchList();
    });

    document.getElementById('notificationBtn')?.addEventListener('click', fetchList);

    if (document.getElementById('notificationCount')) {
        fetchCount();
        fetchList();
        timer = setInterval(fetchCount, pollInterval);
    }
})();
