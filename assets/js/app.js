/* =====================================================================
   LemboEvents — global UI behaviour (vanilla JS, no dependencies)
   ===================================================================== */
(function () {
    'use strict';

    document.documentElement.classList.add('js');
    const BASE = document.querySelector('meta[name="base-url"]')?.content || '';
    const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ------------------------------------------------------------------
    // API helper: JSON fetch with CSRF header and friendly errors
    // ------------------------------------------------------------------
    async function api(path, { method = 'GET', body = null, signal } = {}) {
        const opts = { method, headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }, credentials: 'same-origin', signal };
        if (method !== 'GET') {
            opts.headers['X-CSRF-Token'] = CSRF();
            if (body !== null) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }
        }
        let res;
        try {
            res = await fetch(BASE + '/' + path.replace(/^\//, ''), opts);
        } catch (e) {
            if (e.name === 'AbortError') throw e;
            throw new Error('Network error. Check your connection and try again.');
        }
        let data = null;
        try { data = await res.json(); } catch (_) { /* non-JSON */ }
        if (!res.ok || !data || data.ok === false) {
            const err = new Error((data && data.error) || 'Something went wrong. Please try again.');
            err.status = res.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    // ------------------------------------------------------------------
    // Toasts
    // ------------------------------------------------------------------
    const ICONS = {
        success: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12.5 3 3 5-6"/></svg>',
        error: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>',
        info: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/></svg>'
    };

    function dismissToast(t) {
        if (!t || t.classList.contains('is-leaving')) return;
        t.classList.add('is-leaving');
        setTimeout(() => t.remove(), 260);
    }

    function toast(message, type = 'success', timeout = 4200) {
        const stack = document.getElementById('toastStack');
        if (!stack) return;
        const t = document.createElement('div');
        t.className = 'toast toast-' + type;
        t.setAttribute('role', type === 'error' ? 'alert' : 'status');
        const span = document.createElement('span');
        span.textContent = message;                       // textContent: never inject HTML
        t.innerHTML = ICONS[type] || ICONS.info;
        t.appendChild(span);
        const close = document.createElement('button');
        close.type = 'button'; close.className = 'toast-close'; close.setAttribute('aria-label', 'Dismiss'); close.textContent = '×';
        t.appendChild(close);
        stack.appendChild(t);
        if (timeout) setTimeout(() => dismissToast(t), timeout);
    }

    document.addEventListener('click', (e) => {
        const c = e.target.closest('.toast-close');
        if (c) dismissToast(c.closest('.toast'));
    });
    document.querySelectorAll('.toast[data-autodismiss]').forEach((t) => setTimeout(() => dismissToast(t), 5200));

    // ------------------------------------------------------------------
    // Header: shadow on scroll
    // ------------------------------------------------------------------
    const header = document.getElementById('siteHeader');
    const onScroll = () => header && header.classList.toggle('is-scrolled', window.scrollY > 8);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // ------------------------------------------------------------------
    // Mobile drawer — fixed to the viewport, works at any scroll position
    // ------------------------------------------------------------------
    const drawer = document.getElementById('mobileDrawer');
    const backdrop = document.querySelector('.drawer-backdrop');
    const openBtn = document.querySelector('[data-menu-open]');
    let lastFocus = null;

    function openMenu() {
        if (!drawer) return;
        lastFocus = document.activeElement;
        backdrop.hidden = false;
        requestAnimationFrame(() => backdrop.classList.add('is-visible'));
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        openBtn?.setAttribute('aria-expanded', 'true');
        document.body.classList.add('menu-open');
        setTimeout(() => drawer.querySelector('button, a, input')?.focus(), 50);
    }
    function closeMenu() {
        if (!drawer || !drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.classList.remove('is-visible');
        openBtn?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('menu-open');
        setTimeout(() => { backdrop.hidden = true; }, 250);
        lastFocus?.focus?.();
    }
    openBtn?.addEventListener('click', openMenu);
    document.querySelectorAll('[data-menu-close]').forEach((el) => el.addEventListener('click', closeMenu));
    window.addEventListener('resize', () => { if (window.innerWidth > 960) closeMenu(); });

    // Keep keyboard focus inside the drawer while it is open
    drawer?.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') return;
        const f = drawer.querySelectorAll('a[href], button:not([disabled]), input');
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    // ------------------------------------------------------------------
    // Dropdowns
    // ------------------------------------------------------------------
    function closeDropdowns(except) {
        document.querySelectorAll('[data-dropdown].is-open').forEach((d) => {
            if (d !== except) {
                d.classList.remove('is-open');
                d.querySelector('[data-dropdown-toggle]')?.setAttribute('aria-expanded', 'false');
            }
        });
    }
    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-dropdown-toggle]');
        if (toggle) {
            const dd = toggle.closest('[data-dropdown]');
            const open = !dd.classList.contains('is-open');
            closeDropdowns(dd);
            dd.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            if (open && toggle.hasAttribute('data-notif-toggle')) loadNotifications();
            return;
        }
        if (!e.target.closest('.dropdown-panel')) closeDropdowns();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closeDropdowns(); closeMenu(); closeModal(); }
    });

    // ------------------------------------------------------------------
    // Notifications (navbar)
    // ------------------------------------------------------------------
    const notifIcons = {
        booking: '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/>',
        reminder: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        review: '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        cancellation: '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        default: '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>'
    };
    function setUnread(n) {
        document.querySelectorAll('[data-notif-count]').forEach((el) => {
            el.textContent = n > 99 ? '99+' : String(n);
            el.classList.toggle('is-hidden', !n);
        });
    }
    async function loadNotifications() {
        const list = document.querySelector('[data-notif-list]');
        if (!list) return;
        try {
            const data = await api('api/notifications.php?limit=6');
            list.innerHTML = '';
            if (!data.items.length) {
                list.innerHTML = '<div class="notif-empty">You\'re all caught up ✨</div>';
            }
            data.items.forEach((n) => {
                const a = document.createElement('a');
                a.className = 'notif-item' + (n.is_read ? '' : ' unread');
                a.href = n.link ? BASE + '/' + n.link : '#';
                a.dataset.id = n.id;
                const ic = notifIcons[n.type] || (n.type.indexOf('cancel') > -1 ? notifIcons.cancellation : notifIcons.default);
                a.innerHTML = '<span class="notif-dot"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + ic + '</svg></span><div><strong></strong><p></p><time></time></div>';
                a.querySelector('strong').textContent = n.title;
                a.querySelector('p').textContent = n.message;
                a.querySelector('time').textContent = n.time_ago;
                list.appendChild(a);
            });
            setUnread(data.unread);
        } catch (err) {
            list.innerHTML = '<div class="notif-empty">Could not load notifications.</div>';
        }
    }
    document.addEventListener('click', async (e) => {
        const item = e.target.closest('.notif-item.unread');
        if (item && item.dataset.id) {
            api('api/notifications.php', { method: 'POST', body: { action: 'read', id: Number(item.dataset.id) } }).catch(() => {});
        }
        const all = e.target.closest('[data-notif-read-all]');
        if (all) {
            try {
                await api('api/notifications.php', { method: 'POST', body: { action: 'read_all' } });
                document.querySelectorAll('.notif-item.unread, .notif-page-item.unread').forEach((el) => el.classList.remove('unread'));
                setUnread(0);
                toast('All notifications marked as read');
            } catch (err) { toast(err.message, 'error'); }
        }
    });
    // Light polling so the badge stays fresh without reloads
    if (document.querySelector('[data-notif-count]')) {
        setInterval(() => {
            if (document.hidden) return;
            api('api/notifications.php?count=1').then((d) => setUnread(d.unread)).catch(() => {});
        }, 60000);
    }

    // ------------------------------------------------------------------
    // Favorites (event cards + event page)
    // ------------------------------------------------------------------
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-favorite]');
        if (!btn) return;
        e.preventDefault();
        if (btn.dataset.busy) return;
        btn.dataset.busy = '1';
        const id = Number(btn.dataset.favorite);
        try {
            const data = await api('api/favorites.php', { method: 'POST', body: { event_id: id } });
            document.querySelectorAll('[data-favorite="' + id + '"]').forEach((b) => {
                b.classList.toggle('is-active', data.favorited);
                b.setAttribute('aria-pressed', String(data.favorited));
                b.classList.remove('pop'); void b.offsetWidth; b.classList.add('pop');
                const label = b.getAttribute('aria-label') || '';
                b.setAttribute('aria-label', label.replace(/^(Remove from favorites|Save to favorites)/, data.favorited ? 'Remove from favorites' : 'Save to favorites'));
            });
            toast(data.favorited ? 'Saved to your favorites' : 'Removed from favorites', 'success', 2200);
            if (!data.favorited && btn.closest('[data-remove-on-unfavorite]')) {
                const card = btn.closest('.event-card');
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity = '0'; card.style.transform = 'scale(.96)';
                setTimeout(() => { card.remove(); if (!document.querySelector('[data-remove-on-unfavorite] .event-card')) location.reload(); }, 300);
            }
        } catch (err) {
            if (err.status === 401) {
                toast('Log in to save events you love.', 'info');
                setTimeout(() => { location.href = BASE + '/login.php?redirect=' + encodeURIComponent(location.pathname.replace(BASE + '/', '') + location.search); }, 1200);
            } else {
                toast(err.message, 'error');
            }
        } finally {
            delete btn.dataset.busy;
        }
    });

    // ------------------------------------------------------------------
    // Modals: [data-modal-open="id"], [data-modal-close]
    // ------------------------------------------------------------------
    let openModalEl = null;
    function openModal(id, trigger) {
        const m = document.getElementById(id);
        if (!m) return;
        closeModal();
        m.hidden = false;
        openModalEl = m;
        m._trigger = trigger;
        document.body.classList.add('modal-open');
        m.dispatchEvent(new CustomEvent('modal:open', { detail: { trigger } }));
        setTimeout(() => m.querySelector('input:not([type=hidden]), textarea, select, button')?.focus(), 60);
    }
    function closeModal() {
        if (!openModalEl) return;
        openModalEl.hidden = true;
        document.body.classList.remove('modal-open');
        openModalEl._trigger?.focus?.();
        openModalEl = null;
    }
    document.addEventListener('click', (e) => {
        const o = e.target.closest('[data-modal-open]');
        if (o) { e.preventDefault(); openModal(o.dataset.modalOpen, o); return; }
        if (e.target.closest('[data-modal-close]') || e.target.classList.contains('modal-backdrop')) closeModal();
    });

    // Confirmation dialog for destructive forms: <form data-confirm="Are you sure?">
    function confirmDialog(message, { title = 'Are you sure?', confirmText = 'Confirm', danger = true } = {}) {
        return new Promise((resolve) => {
            const wrap = document.createElement('div');
            wrap.className = 'modal';
            wrap.innerHTML = '<div class="modal-backdrop"></div><div class="modal-dialog" role="alertdialog" aria-modal="true" aria-labelledby="cfTitle"><div class="modal-head"><h2 id="cfTitle"></h2></div><div class="modal-body"><p class="muted mb-0"></p></div><div class="modal-foot"><button type="button" class="btn btn-soft" data-x="0">Cancel</button><button type="button" class="btn" data-x="1"></button></div></div>';
            wrap.querySelector('h2').textContent = title;
            wrap.querySelector('p').textContent = message;
            const ok = wrap.querySelector('[data-x="1"]');
            ok.textContent = confirmText;
            ok.classList.add(danger ? 'btn-danger' : 'btn-primary');
            document.body.appendChild(wrap);
            document.body.classList.add('modal-open');
            ok.focus();
            const done = (v) => { wrap.remove(); document.body.classList.remove('modal-open'); resolve(v); };
            wrap.addEventListener('click', (e) => {
                const b = e.target.closest('[data-x]');
                if (b) done(b.dataset.x === '1');
                else if (e.target.classList.contains('modal-backdrop')) done(false);
            });
            wrap.addEventListener('keydown', (e) => { if (e.key === 'Escape') { e.stopPropagation(); done(false); } });
        });
    }
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (form.dataset.confirm && !form.dataset.confirmed) {
            e.preventDefault();
            const submitter = e.submitter;
            const ok = await confirmDialog(form.dataset.confirm, {
                title: form.dataset.confirmTitle || 'Are you sure?',
                confirmText: form.dataset.confirmButton || 'Confirm',
                danger: form.dataset.confirmTone !== 'neutral'
            });
            if (ok) {
                form.dataset.confirmed = '1';
                if (submitter && submitter.name) {
                    const h = document.createElement('input');
                    h.type = 'hidden'; h.name = submitter.name; h.value = submitter.value;
                    form.appendChild(h);
                }
                form.submit();
            }
            return;
        }
        // Loading state on submit buttons
        if (!e.defaultPrevented && form.matches('[data-loading]')) {
            const btn = e.submitter || form.querySelector('[type=submit]');
            btn?.classList.add('is-loading');
        }
    }, true);

    // ------------------------------------------------------------------
    // Client-side validation (server validates everything again)
    // ------------------------------------------------------------------
    function fieldError(input, msg) {
        const field = input.closest('.field') || input.parentElement;
        let el = field.querySelector('.field-error.js');
        if (!msg) { field.classList.remove('has-error'); el?.remove(); input.removeAttribute('aria-invalid'); return; }
        field.classList.add('has-error');
        input.setAttribute('aria-invalid', 'true');
        if (!el) { el = document.createElement('span'); el.className = 'field-error js'; field.appendChild(el); }
        el.textContent = msg;
    }
    function validateInput(input) {
        const v = input.value.trim();
        if (input.disabled || input.type === 'hidden') return true;
        if (input.required && !v && input.type !== 'checkbox') { fieldError(input, 'This field is required.'); return false; }
        if (input.type === 'checkbox' && input.required && !input.checked) { fieldError(input, 'Please confirm to continue.'); return false; }
        if (v && input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { fieldError(input, 'Enter a valid email address.'); return false; }
        if (v && input.minLength > 0 && v.length < input.minLength) { fieldError(input, 'Must be at least ' + input.minLength + ' characters.'); return false; }
        if (v && input.dataset.password !== undefined && (!/[A-Za-z]/.test(v) || !/\d/.test(v))) { fieldError(input, 'Use letters and numbers.'); return false; }
        if (input.dataset.match) {
            const other = document.getElementById(input.dataset.match);
            if (other && other.value !== input.value) { fieldError(input, 'Passwords do not match.'); return false; }
        }
        if (v && input.type === 'number') {
            const n = Number(v);
            if (input.min !== '' && n < Number(input.min)) { fieldError(input, 'Minimum is ' + input.min + '.'); return false; }
            if (input.max !== '' && n > Number(input.max)) { fieldError(input, 'Maximum is ' + input.max + '.'); return false; }
        }
        if (v && input.pattern && !new RegExp('^(?:' + input.pattern + ')$').test(v)) { fieldError(input, input.title || 'Invalid format.'); return false; }
        fieldError(input, null);
        return true;
    }
    document.querySelectorAll('form[data-validate]').forEach((form) => {
        form.setAttribute('novalidate', '');
        form.addEventListener('submit', (e) => {
            let ok = true;
            form.querySelectorAll('input, select, textarea').forEach((i) => { if (!validateInput(i)) ok = false; });
            if (!ok) {
                e.preventDefault();
                e.stopImmediatePropagation();
                form.querySelector('[aria-invalid="true"]')?.focus();
                form.querySelectorAll('.is-loading').forEach((b) => b.classList.remove('is-loading'));
            }
        });
        form.addEventListener('blur', (e) => { if (e.target.matches('input, select, textarea') && e.target.value) validateInput(e.target); }, true);
        form.addEventListener('input', (e) => { if (e.target.closest('.has-error')) validateInput(e.target); });
    });

    // Password visibility toggle + strength meter
    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-toggle-password]');
        if (!t) return;
        const input = document.getElementById(t.dataset.togglePassword);
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        t.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
    });
    document.querySelectorAll('[data-strength]').forEach((input) => {
        const meter = document.querySelector(input.dataset.strength + ' i');
        input.addEventListener('input', () => {
            const v = input.value;
            let s = 0;
            if (v.length >= 8) s++;
            if (v.length >= 12) s++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
            if (/\d/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            const pct = Math.min(100, s * 20);
            meter.style.width = pct + '%';
            meter.style.background = pct < 40 ? 'var(--danger)' : pct < 80 ? '#f59e0b' : 'var(--success)';
        });
    });

    // File inputs with image preview
    document.querySelectorAll('.file-drop input[type=file]').forEach((input) => {
        const drop = input.closest('.file-drop');
        const max = Number(input.dataset.maxBytes || 0);
        ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.add('is-drag')));
        ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.remove('is-drag')));
        input.addEventListener('change', () => {
            const f = input.files[0];
            if (!f) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) { toast('Only JPG, PNG or WebP images.', 'error'); input.value = ''; return; }
            if (max && f.size > max) { toast('Image is too large (max ' + Math.round(max / 1048576) + ' MB).', 'error'); input.value = ''; return; }
            let img = drop.querySelector('img');
            if (!img) { img = document.createElement('img'); drop.prepend(img); }
            img.alt = 'Selected image preview';
            img.src = URL.createObjectURL(f);
            drop.querySelector('.file-drop-text')?.setAttribute('hidden', '');
        });
    });

    // ------------------------------------------------------------------
    // Share & copy
    // ------------------------------------------------------------------
    document.addEventListener('click', async (e) => {
        const s = e.target.closest('[data-share]');
        if (s) {
            const data = { title: s.dataset.shareTitle || document.title, url: s.dataset.share || location.href };
            if (navigator.share) {
                try { await navigator.share(data); } catch (_) { /* cancelled */ }
            } else {
                try { await navigator.clipboard.writeText(data.url); toast('Link copied to clipboard'); }
                catch (_) { toast('Copy this link: ' + data.url, 'info', 8000); }
            }
        }
        const c = e.target.closest('[data-copy]');
        if (c) {
            try { await navigator.clipboard.writeText(c.dataset.copy); toast('Copied to clipboard', 'success', 2000); } catch (_) {}
        }
        const p = e.target.closest('[data-print]');
        if (p) window.print();
    });

    // Demo account quick-fill on the login page
    document.addEventListener('click', (e) => {
        const d = e.target.closest('[data-demo-login]');
        if (!d) return;
        const form = document.querySelector('form[data-login-form]');
        if (!form) return;
        form.querySelector('[name=email]').value = d.dataset.demoLogin;
        form.querySelector('[name=password]').value = d.dataset.demoPassword;
        form.querySelector('[type=submit]').focus();
    });

    // ------------------------------------------------------------------
    // Reveal-on-scroll
    // ------------------------------------------------------------------
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); } });
        }, { rootMargin: '0px 0px -60px 0px' });
        document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
    } else {
        document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
    }

    // Image fallback for broken remote covers
    document.addEventListener('error', (e) => {
        const img = e.target;
        if (img.tagName === 'IMG' && !img.dataset.fallback) {
            img.dataset.fallback = '1';
            img.src = BASE + '/assets/images/event-placeholder.svg';
        }
    }, true);

    // Auto-submit selects: <select data-autosubmit>
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-autosubmit]')) e.target.form?.submit();
    });

    // Expose helpers for page scripts
    window.Lembo = { api, toast, confirm: confirmDialog, openModal, closeModal, base: BASE, csrf: CSRF };
})();
