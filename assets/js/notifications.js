/* Notifications page: mark read / delete without reloading. */
(function () {
    'use strict';
    const { api, toast } = window.Lembo;
    const setCount = (n) => document.querySelectorAll('[data-notif-count]').forEach((el) => { el.textContent = n > 99 ? '99+' : n; el.classList.toggle('is-hidden', !n); });

    document.addEventListener('click', async (e) => {
        const mark = e.target.closest('[data-notif-mark]');
        const del = e.target.closest('[data-notif-delete]');
        const open = e.target.closest('[data-notif-open]');
        if (open) {
            api('api/notifications.php', { method: 'POST', body: { action: 'read', id: Number(open.dataset.notifOpen) } }).catch(() => {});
            return;
        }
        if (!mark && !del) return;
        const id = Number((mark || del).dataset.notifMark || (mark || del).dataset.notifDelete);
        const row = document.querySelector('[data-notif-row="' + id + '"]');
        try {
            const d = await api('api/notifications.php', { method: 'POST', body: { action: mark ? 'read' : 'delete', id } });
            setCount(d.unread);
            if (mark) { row.classList.remove('unread'); mark.remove(); }
            else {
                row.style.transition = 'opacity .25s'; row.style.opacity = '0';
                setTimeout(() => row.remove(), 250);
                toast('Notification deleted', 'success', 1800);
            }
        } catch (err) { toast(err.message, 'error'); }
    });
})();
