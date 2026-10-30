/* LemboBot widget. Talks only to api/chatbot.php — no API key ever reaches the browser. */
(function () {
    'use strict';
    const root = document.getElementById('lembobot');
    if (!root || !window.Lembo) return;
    const { api } = window.Lembo;
    const toggle = document.getElementById('lembobotToggle');
    const panel = document.getElementById('lembobotPanel');
    const list = root.querySelector('[data-bot-messages]');
    const form = root.querySelector('[data-bot-form]');
    const input = root.querySelector('[data-bot-input]');
    const sendBtn = root.querySelector('[data-bot-send]');
    const statusEl = root.querySelector('[data-bot-status]');
    const suggestions = root.querySelector('[data-bot-suggestions]');
    let loadedHistory = false;
    let sending = false;

    try { if (localStorage.getItem('lembobot_seen')) root.classList.add('seen'); } catch (_) {}

    // Escape first, then apply a tiny safe markdown subset (bold, bullets, line breaks)
    function format(text) {
        const esc = String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        return esc.split(/\n{2,}/).map((p) => '<p>' + p
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|\n)\s*[-*]\s+/g, '$1• ')
            .replace(/\n/g, '<br>') + '</p>').join('');
    }

    function scrollDown() { list.scrollTop = list.scrollHeight; }

    function addMessage(sender, text, opts = {}) {
        const wrap = document.createElement('div');
        wrap.className = 'bot-msg ' + sender + (opts.error ? ' error' : '');
        const bubble = document.createElement('div');
        bubble.className = 'bot-bubble';
        bubble.innerHTML = sender === 'user' ? '' : format(text);
        if (sender === 'user') bubble.textContent = text;
        wrap.appendChild(bubble);
        if (opts.events && opts.events.length) {
            const box = document.createElement('div');
            box.className = 'bot-events';
            opts.events.forEach((ev) => {
                const a = document.createElement('a');
                a.className = 'bot-event';
                a.href = ev.url;
                const img = document.createElement('img');
                img.src = ev.image; img.alt = ''; img.loading = 'lazy';
                const info = document.createElement('div');
                const t = document.createElement('strong'); t.textContent = ev.title;
                const d = document.createElement('span'); d.textContent = ev.date + ' · ' + ev.place;
                const p = document.createElement('em'); p.textContent = ev.sold_out ? 'Sold out' : ev.price;
                info.append(t, d, p);
                a.append(img, info);
                box.appendChild(a);
            });
            wrap.appendChild(box);
        }
        if (opts.meta) {
            const m = document.createElement('span');
            m.className = 'bot-meta';
            m.textContent = opts.meta;
            wrap.appendChild(m);
        }
        list.appendChild(wrap);
        scrollDown();
        return wrap;
    }

    function setStatus(s) {
        if (!s) return;
        statusEl.classList.toggle('online', !!s.online);
        statusEl.classList.toggle('basic', !s.online);
        statusEl.querySelector('span').textContent = s.label;
    }

    async function loadHistory() {
        if (loadedHistory) return;
        loadedHistory = true;
        try {
            const d = await api('api/chatbot.php?action=history');
            setStatus(d.status);
            d.messages.forEach((m) => addMessage(m.sender === 'bot' ? 'bot' : 'user', m.message));
            if (d.messages.length) suggestions.hidden = true;
        } catch (_) {
            setStatus({ online: false, label: 'Offline · try again later' });
        }
    }

    function open() {
        panel.hidden = false;
        root.classList.add('is-open', 'seen');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close LemboBot assistant');
        try { localStorage.setItem('lembobot_seen', '1'); } catch (_) {}
        loadHistory();
        setTimeout(() => input.focus(), 80);
    }
    function close() {
        panel.hidden = true;
        root.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open LemboBot assistant');
        toggle.focus();
    }
    toggle.addEventListener('click', () => (panel.hidden ? open() : close()));
    root.querySelector('[data-bot-close]').addEventListener('click', close);
    panel.addEventListener('keydown', (e) => { if (e.key === 'Escape') { e.stopPropagation(); close(); } });

    root.querySelector('[data-bot-clear]').addEventListener('click', async () => {
        try { await api('api/chatbot.php', { method: 'POST', body: { action: 'clear' } }); } catch (_) {}
        [...list.children].slice(1).forEach((c) => c.remove());
        suggestions.hidden = false;
    });

    async function send(text) {
        text = text.trim();
        if (!text || sending) return;
        if (text.length > 500) { addMessage('bot', 'That message is a bit long — please keep it under 500 characters.', { error: true }); return; }
        sending = true;
        sendBtn.disabled = true;
        suggestions.hidden = true;
        addMessage('user', text);
        input.value = '';
        input.style.height = '';
        const typing = document.createElement('div');
        typing.className = 'bot-msg bot';
        typing.innerHTML = '<div class="bot-bubble bot-typing" aria-label="LemboBot is typing"><i></i><i></i><i></i></div>';
        list.appendChild(typing);
        scrollDown();
        try {
            const d = await api('api/chatbot.php', { method: 'POST', body: { action: 'send', message: text } });
            typing.remove();
            addMessage('bot', d.reply, { events: d.events });
            setStatus(d.status);
        } catch (err) {
            typing.remove();
            const msg = err.status === 429 ? err.message : (err.status === 403 ? 'Your session expired. Please refresh the page.' : 'Sorry, I could not reply just now. ' + err.message);
            addMessage('bot', msg, { error: true });
        } finally {
            sending = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }

    form.addEventListener('submit', (e) => { e.preventDefault(); send(input.value); });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); }
    });
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(110, input.scrollHeight) + 'px';
    });
    suggestions.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-bot-suggest]');
        if (chip) send(chip.textContent);
    });

    // Status for the header dot without loading the whole history
    api('api/chatbot.php?action=status').then(setStatus).catch(() => setStatus({ online: false, label: 'Offline' }));
})();
