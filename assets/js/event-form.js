/* Event editor: add/remove ticket type rows. */
(function () {
    'use strict';
    const list = document.querySelector('[data-tt-list]');
    const tpl = document.querySelector('[data-tt-template]');
    if (!list || !tpl) return;
    let idx = list.querySelectorAll('[data-tt-row]').length + 100;

    document.querySelector('[data-add-ticket]')?.addEventListener('click', () => {
        if (list.querySelectorAll('[data-tt-row]').length >= 12) { window.Lembo.toast('You can add up to 12 ticket types.', 'info'); return; }
        const html = tpl.innerHTML.replace(/__i__/g, String(idx++));
        list.insertAdjacentHTML('beforeend', html);
        list.lastElementChild.querySelector('input[name$="[name]"]').focus();
    });
    list.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-ticket]');
        if (!btn) return;
        if (list.querySelectorAll('[data-tt-row]').length <= 1) { window.Lembo.toast('An event needs at least one ticket type.', 'info'); return; }
        btn.closest('[data-tt-row]').remove();
    });
    // Mirror the start date into the end date when the end is earlier
    const sd = document.getElementById('start_date'), ed = document.getElementById('end_date');
    sd?.addEventListener('change', () => { if (!ed.value || ed.value < sd.value) ed.value = sd.value; });
})();
