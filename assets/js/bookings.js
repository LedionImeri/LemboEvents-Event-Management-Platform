/* Attendee bookings: cancel via AJAX with a confirmation dialog. */
(function () {
    'use strict';
    const { api, toast, confirm } = window.Lembo;
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-cancel-booking]');
        if (!btn) return;
        const refund = btn.dataset.total ? ' You will receive a full refund of ' + btn.dataset.total + ' (demo).' : '';
        const ok = await confirm('Cancel booking ' + btn.dataset.ref + '? Your tickets will be voided and released to other fans.' + refund,
            { title: 'Cancel this booking?', confirmText: 'Yes, cancel booking' });
        if (!ok) return;
        btn.classList.add('is-loading');
        try {
            const d = await api('api/bookings.php', { method: 'POST', body: { action: 'cancel', booking_id: Number(btn.dataset.cancelBooking) } });
            toast(d.message);
            const row = btn.closest('[data-booking-row]');
            row.style.transition = 'opacity .35s, transform .35s';
            row.style.opacity = '0';
            row.style.transform = 'translateX(20px)';
            setTimeout(() => location.reload(), 700);
        } catch (err) {
            toast(err.message, 'error');
            btn.classList.remove('is-loading');
        }
    });
})();
