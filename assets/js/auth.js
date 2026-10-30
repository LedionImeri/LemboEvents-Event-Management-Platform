/* Register page: toggle organizer-only fields + live email availability check. */
(function () {
    'use strict';
    const { api } = window.Lembo;
    const orgField = document.querySelector('[data-org-only]');
    const attOnly = document.querySelector('[data-attendee-only]');
    document.querySelectorAll('[data-role-switch]').forEach((r) => r.addEventListener('change', () => {
        const org = r.value === 'organizer' && r.checked;
        if (!r.checked) return;
        orgField.hidden = !org;
        const input = orgField.querySelector('input');
        input.disabled = !org;
        input.required = org;
        if (attOnly) attOnly.hidden = org;
        document.querySelector('.auth-card h1').textContent = org ? 'Become an organizer' : 'Create account';
    }));

    const email = document.getElementById('email');
    let t;
    email?.addEventListener('input', () => {
        clearTimeout(t);
        t = setTimeout(async () => {
            const v = email.value.trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return;
            try {
                const d = await api('api/auth.php?check_email=' + encodeURIComponent(v));
                const field = email.closest('.field');
                let el = field.querySelector('.field-error.avail');
                if (!d.available) {
                    if (!el) { el = document.createElement('span'); el.className = 'field-error avail'; field.appendChild(el); }
                    el.textContent = 'This email is already registered. Try logging in instead.';
                    field.classList.add('has-error');
                } else if (el) { el.remove(); field.classList.remove('has-error'); }
            } catch (_) { /* ignore */ }
        }, 450);
    });
})();
