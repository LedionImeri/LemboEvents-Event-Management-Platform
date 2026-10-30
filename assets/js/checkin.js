/* QR check-in scanner: camera (BarcodeDetector or jsQR) + manual entry. All validation happens server-side. */
(function () {
    'use strict';
    const root = document.querySelector('[data-scanner]');
    if (!root) return;
    const { api, toast } = window.Lembo;
    const eventId = Number(root.dataset.event);
    const video = root.querySelector('[data-video]');
    const frame = root.querySelector('[data-frame]');
    const idle = root.querySelector('[data-idle]');
    const result = root.querySelector('[data-result]');
    const log = root.querySelector('[data-log]');
    const stopBtn = root.querySelector('[data-stop-camera]');
    const switchBtn = root.querySelector('[data-switch-camera]');
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    let stream = null, detector = null, raf = null, busy = false, facing = 'environment';
    let lastCode = '', lastAt = 0, scans = 0;

    const ICON = { valid: '✓', used: '⚠', invalid: '✕' };

    function beep(ok) {
        try {
            const ac = new (window.AudioContext || window.webkitAudioContext)();
            const o = ac.createOscillator(), g = ac.createGain();
            o.frequency.value = ok ? 880 : 220; o.type = ok ? 'sine' : 'square';
            g.gain.value = 0.08; o.connect(g); g.connect(ac.destination);
            o.start(); o.stop(ac.currentTime + (ok ? 0.15 : 0.35));
        } catch (_) {}
        if (navigator.vibrate) navigator.vibrate(ok ? 80 : [80, 60, 80]);
    }

    function el(tag, cls, text) { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; }

    function showResult(d) {
        result.className = 'scan-result ' + d.result;
        result.innerHTML = '';
        result.appendChild(el('div', 'sr-icon', ICON[d.result]));
        result.appendChild(el('h2', '', (d.result === 'valid' ? '✓ ' : d.result === 'used' ? '⚠ ' : '✕ ') + d.title));
        result.appendChild(el('p', 'mb-0', d.message));
        if (d.ticket) {
            const grid = el('div', 'scan-details');
            [['Attendee', d.ticket.attendee], ['Ticket type', d.ticket.type], ['Event', d.ticket.event], ['Ticket ID', d.ticket.id], ['Code', d.ticket.code], d.ticket.checked_in_at ? ['Checked in', d.ticket.checked_in_at] : null]
                .filter(Boolean).forEach(([k, v]) => { const c = el('div'); c.appendChild(el('small', '', k)); c.appendChild(el('strong', '', v)); grid.appendChild(c); });
            result.appendChild(grid);
        }
        void result.offsetWidth; result.classList.add('flash');

        if (d.counts) {
            const pct = d.counts.total ? Math.round(d.counts.checked / d.counts.total * 100) : 0;
            document.querySelector('[data-ci-checked]').textContent = d.counts.checked.toLocaleString();
            document.querySelector('[data-ci-total]').textContent = d.counts.total.toLocaleString();
            document.querySelector('[data-ci-pct]').textContent = pct + '%';
            document.querySelector('[data-ci-bar]').style.width = pct + '%';
        }
        log.querySelector('[data-log-empty]')?.remove();
        const item = el('div', 'scan-log-item ' + d.result);
        item.appendChild(el('b', '', ICON[d.result]));
        item.appendChild(el('span', '', (d.ticket ? d.ticket.attendee + ' · ' + d.ticket.type : d.title)));
        item.appendChild(el('time', '', new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })));
        log.prepend(item);
        scans++;
        document.querySelector('[data-log-count]').textContent = scans + (scans === 1 ? ' scan' : ' scans');
        beep(d.result === 'valid');
    }

    async function verify(payload, method) {
        if (busy) return;
        busy = true;
        try {
            const d = await api('api/checkin.php', { method: 'POST', body: { payload, event_id: eventId, method } });
            showResult(d);
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            setTimeout(() => { busy = false; }, 900);
        }
    }

    function onCode(text) {
        const now = Date.now();
        if (text === lastCode && now - lastAt < 4000) return; // same code still in front of the camera
        lastCode = text; lastAt = now;
        verify(text, 'qr');
    }

    async function tick() {
        if (!stream) return;
        if (!busy && video.readyState >= 2) {
            try {
                if (detector) {
                    const codes = await detector.detect(video);
                    if (codes.length) onCode(codes[0].rawValue);
                } else if (window.jsQR) {
                    const w = video.videoWidth, h = video.videoHeight;
                    const scale = Math.min(1, 640 / Math.max(w, h));
                    canvas.width = Math.round(w * scale); canvas.height = Math.round(h * scale);
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
                    if (code && code.data) onCode(code.data);
                }
            } catch (_) { /* frame not ready */ }
        }
        raf = requestAnimationFrame(tick);
    }

    async function start() {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            toast('Camera needs HTTPS or localhost. Use manual code entry instead.', 'error', 7000);
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
        } catch (err) {
            toast(err.name === 'NotAllowedError' ? 'Camera permission denied. Use manual entry or allow camera access.' : 'No camera available on this device.', 'error', 6000);
            return;
        }
        if ('BarcodeDetector' in window) {
            try {
                const formats = await window.BarcodeDetector.getSupportedFormats();
                if (formats.includes('qr_code')) detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            } catch (_) { detector = null; }
        }
        if (!detector && !window.jsQR) { toast('QR decoder failed to load. Use manual entry.', 'error'); }
        video.srcObject = stream;
        await video.play().catch(() => {});
        video.hidden = false; frame.hidden = false; idle.hidden = true;
        stopBtn.hidden = false; switchBtn.hidden = false;
        raf = requestAnimationFrame(tick);
    }

    function stop() {
        cancelAnimationFrame(raf);
        stream?.getTracks().forEach((t) => t.stop());
        stream = null;
        video.hidden = true; frame.hidden = true; idle.hidden = false;
        stopBtn.hidden = true; switchBtn.hidden = true;
    }

    root.querySelector('[data-start-camera]').addEventListener('click', start);
    stopBtn.addEventListener('click', stop);
    switchBtn.addEventListener('click', () => { facing = facing === 'environment' ? 'user' : 'environment'; stop(); start(); });
    document.addEventListener('visibilitychange', () => { if (document.hidden && stream) stop(); });

    root.querySelector('[data-manual-form]').addEventListener('submit', (e) => {
        e.preventDefault();
        const input = e.target.code;
        const v = input.value.trim();
        if (!v) { input.focus(); return; }
        verify(v, 'manual');
        input.value = '';
        input.focus();
    });
})();
