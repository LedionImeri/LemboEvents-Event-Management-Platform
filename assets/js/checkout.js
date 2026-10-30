/* Checkout: card formatting, live card preview, payment method switching. */
(function () {
    'use strict';
    const form = document.querySelector('[data-checkout]');
    if (!form) return;
    const num = form.querySelector('#card_number');
    const exp = form.querySelector('#card_expiry');
    const cvc = form.querySelector('#card_cvc');
    const name = form.querySelector('#card_name');
    const cardFields = form.querySelector('[data-card-fields]');
    const walletNote = form.querySelector('[data-wallet-note]');

    num?.addEventListener('input', () => {
        const d = num.value.replace(/\D/g, '').slice(0, 19);
        num.value = d.replace(/(.{4})/g, '$1 ').trim();
        const shown = (d + '•'.repeat(Math.max(0, 16 - d.length))).replace(/(.{4})/g, '$1 ').trim();
        form.querySelector('[data-cp-number]').textContent = shown;
        form.querySelector('[data-cp-brand]').textContent = /^5[1-5]/.test(d) ? 'MASTERCARD' : /^3[47]/.test(d) ? 'AMEX' : 'VISA';
    });
    exp?.addEventListener('input', (e) => {
        let d = exp.value.replace(/\D/g, '').slice(0, 4);
        if (d.length >= 3) d = d.slice(0, 2) + '/' + d.slice(2);
        else if (d.length === 2 && e.inputType !== 'deleteContentBackward') d += '/';
        exp.value = d;
        form.querySelector('[data-cp-exp]').textContent = d || 'MM/YY';
    });
    cvc?.addEventListener('input', () => { cvc.value = cvc.value.replace(/\D/g, '').slice(0, 4); });
    name?.addEventListener('input', () => { form.querySelector('[data-cp-name]').textContent = name.value.toUpperCase() || 'YOUR NAME'; });
    if (name) form.querySelector('[data-cp-name]').textContent = name.value.toUpperCase() || 'YOUR NAME';

    form.querySelectorAll('[name=payment_method]').forEach((r) => r.addEventListener('change', () => {
        const card = form.querySelector('[name=payment_method]:checked').value === 'card';
        cardFields.hidden = !card;
        walletNote.hidden = card;
        cardFields.querySelectorAll('input').forEach((i) => { i.disabled = !card; });
    }));
    form.querySelector('[name=payment_method]:checked')?.dispatchEvent(new Event('change'));
})();
