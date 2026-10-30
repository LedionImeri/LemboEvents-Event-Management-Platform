/* Event page: ticket quantity steppers, live totals, reviews & reports. Also used on attendee pages for reviews. */
(function () {
    'use strict';
    const { api, toast, closeModal } = window.Lembo;
    const fmt = (n) => '€' + n.toFixed(2);

    // ---------------- Ticket selection ----------------
    const form = document.querySelector('[data-ticket-form]');
    if (form) {
        const rate = Number(form.dataset.feeRate || 0);
        const btn = form.querySelector('[data-book-btn]');
        const MAX_ORDER = 10;

        function update() {
            let count = 0, subtotal = 0;
            form.querySelectorAll('.ticket-option[data-tt]').forEach((opt) => {
                const input = opt.querySelector('input[type=number]');
                if (!input) return;
                const q = Math.max(0, Math.min(Number(input.max), parseInt(input.value, 10) || 0));
                input.value = q;
                count += q;
                subtotal += q * Number(opt.dataset.price);
                opt.classList.toggle('is-selected', q > 0);
                opt.querySelector('[data-qty-minus]').disabled = q <= 0;
                opt.querySelector('[data-qty-plus]').disabled = q >= Number(input.max);
            });
            const fee = subtotal > 0 ? Math.round(subtotal * rate * 100) / 100 : 0;
            form.querySelector('[data-sum-count]').textContent = count;
            form.querySelector('[data-sum-subtotal]').textContent = fmt(subtotal);
            form.querySelector('[data-sum-fee]').textContent = fmt(fee);
            form.querySelector('[data-sum-total]').textContent = subtotal > 0 ? fmt(subtotal + fee) : (count ? 'Free' : fmt(0));
            btn.disabled = count === 0 || count > MAX_ORDER;
            btn.lastChild.textContent = count > MAX_ORDER ? ' Max ' + MAX_ORDER + ' per order' : (count ? ' Book ' + count + (count === 1 ? ' ticket' : ' tickets') : ' Book tickets');
        }

        form.addEventListener('click', (e) => {
            const plus = e.target.closest('[data-qty-plus]');
            const minus = e.target.closest('[data-qty-minus]');
            if (!plus && !minus) return;
            const input = e.target.closest('.qty').querySelector('input');
            const total = [...form.querySelectorAll('.qty input')].reduce((s, i) => s + (parseInt(i.value, 10) || 0), 0);
            if (plus && total >= MAX_ORDER) { toast('You can book up to ' + MAX_ORDER + ' tickets per order.', 'info'); return; }
            input.value = (parseInt(input.value, 10) || 0) + (plus ? 1 : -1);
            update();
        });
        form.addEventListener('input', update);
        form.addEventListener('submit', (e) => {
            // Strip zero quantities so the checkout URL stays clean
            form.querySelectorAll('.qty input').forEach((i) => { i.disabled = !(parseInt(i.value, 10) > 0); });
            setTimeout(() => form.querySelectorAll('.qty input').forEach((i) => { i.disabled = false; }), 500);
            btn.classList.add('is-loading');
        });
        // Live availability: refresh "X left" every 45s and when the tab regains focus
        const eventId = form.querySelector('[name=event]').value;
        async function refreshAvailability() {
            if (document.hidden) return;
            try {
                const d = await api('api/tickets.php?event=' + eventId);
                d.ticket_types.forEach((t) => {
                    const opt = form.querySelector('.ticket-option[data-tt="' + t.id + '"]');
                    const input = opt?.querySelector('.qty input');
                    const label = opt?.querySelector('.tt-left');
                    if (!opt || !input) return;
                    const max = Math.min(t.available, t.max_per_order);
                    input.max = max;
                    if (t.state !== 'on_sale') {
                        opt.classList.add('is-unavailable');
                        input.value = 0;
                        if (label) { label.textContent = t.state === 'sold_out' ? 'Just sold out' : 'Not available'; label.classList.add('low'); }
                        opt.querySelector('.qty').hidden = true;
                    } else if (label) {
                        label.textContent = t.available <= 20 ? 'Only ' + t.available + ' left!' : t.available.toLocaleString() + ' available';
                        label.classList.toggle('low', t.available <= 20);
                    }
                });
                update();
            } catch (_) { /* keep current numbers */ }
        }
        setInterval(refreshAvailability, 45000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshAvailability(); });

        // Preselect the first available ticket for a faster flow
        const first = form.querySelector('.ticket-option:not(.is-unavailable) .qty input');
        if (first && Number(first.max) > 0) first.value = 1;
        update();
        window.addEventListener('pageshow', () => { btn.classList.remove('is-loading'); update(); });
    }

    // ---------------- Review modal ----------------
    const reviewModal = document.getElementById('reviewModal');
    reviewModal?.addEventListener('modal:open', (e) => {
        const t = e.detail.trigger;
        const f = reviewModal.querySelector('form');
        f.reset();
        f.event_id.value = t.dataset.reviewEvent;
        reviewModal.querySelector('[data-review-event-name]').textContent = t.dataset.reviewTitle || '';
    });
    document.querySelector('[data-review-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const rating = Number(f.querySelector('[name=rating]:checked')?.value || 0);
        const comment = f.comment.value.trim();
        if (!rating) { toast('Please choose a star rating.', 'error'); return; }
        if (comment.length < 10) { toast('Please write at least 10 characters.', 'error'); f.comment.focus(); return; }
        const btn = f.querySelector('[type=submit]');
        btn.classList.add('is-loading');
        try {
            const d = await api('api/reviews.php', { method: 'POST', body: { event_id: Number(f.event_id.value), rating, title: f.title.value.trim(), comment } });
            closeModal();
            toast(d.message);
            setTimeout(() => location.reload(), 900);
        } catch (err) { toast(err.message, 'error'); }
        finally { btn.classList.remove('is-loading'); }
    });

    // ---------------- Report modal ----------------
    const reportModal = document.getElementById('reportModal');
    reportModal?.addEventListener('modal:open', (e) => {
        const t = e.detail.trigger;
        const f = reportModal.querySelector('form');
        f.reset();
        f.target_type.value = t.dataset.reportType;
        f.target_id.value = t.dataset.reportId;
        reportModal.querySelector('#reportModalTitle').textContent = 'Report ' + t.dataset.reportType;
    });
    document.querySelector('[data-report-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const btn = f.querySelector('[type=submit]');
        btn.classList.add('is-loading');
        try {
            const d = await api('api/reports.php', { method: 'POST', body: { target_type: f.target_type.value, target_id: Number(f.target_id.value), reason: f.reason.value, details: f.details.value.trim() } });
            closeModal();
            toast(d.message);
        } catch (err) { toast(err.message, 'error'); }
        finally { btn.classList.remove('is-loading'); }
    });
})();
