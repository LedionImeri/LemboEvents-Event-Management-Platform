/* Organizer attendee list: manual check-in via the check-in API. */
(function () {
    'use strict';
    const { api, toast, confirm } = window.Lembo;
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-manual-checkin]');
        if (!btn) return;
        const name = btn.closest('tr').querySelector('strong').textContent;
        if (!await confirm('Mark ' + name + "'s ticket as checked in?", { title: 'Manual check-in', confirmText: 'Check in', danger: false })) return;
        btn.classList.add('is-loading');
        try {
            const d = await api('api/checkin.php', { method: 'POST', body: { payload: btn.dataset.manualCheckin, method: 'manual' } });
            if (d.result === 'valid') {
                toast('✓ ' + d.ticket.attendee + ' checked in');
                btn.closest('td').previousElementSibling.innerHTML = '<span class="badge badge-info">Checked In</span>';
                btn.replaceWith('—');
            } else {
                toast(d.message, d.result === 'used' ? 'info' : 'error');
                btn.classList.remove('is-loading');
            }
        } catch (err) { toast(err.message, 'error'); btn.classList.remove('is-loading'); }
    });
})();
