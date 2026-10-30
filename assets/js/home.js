/* Homepage: "events near you" geolocation + newsletter signup. */
(function () {
    'use strict';
    const { api, toast } = window.Lembo;

    const locBtn = document.querySelector('[data-near-locate]');
    const grid = document.querySelector('[data-near-grid]');
    const label = document.querySelector('[data-near-label]');
    locBtn?.addEventListener('click', () => {
        if (!navigator.geolocation) { toast('Your browser does not support location.', 'error'); return; }
        locBtn.classList.add('is-loading');
        navigator.geolocation.getCurrentPosition(async (pos) => {
            try {
                const lat = pos.coords.latitude.toFixed(4), lng = pos.coords.longitude.toFixed(4);
                const data = await api('api/events.php?limit=4&lat=' + lat + '&lng=' + lng);
                if (data.count) {
                    grid.innerHTML = data.html;
                    label.textContent = 'Closest events to you' + (data.nearest_city ? ' — starting with ' + data.nearest_city : '') + '.';
                }
            } catch (err) { toast(err.message, 'error'); }
            finally { locBtn.classList.remove('is-loading'); }
        }, () => {
            locBtn.classList.remove('is-loading');
            toast('Location permission was denied. Showing events in Prishtina.', 'info');
        }, { timeout: 8000, maximumAge: 600000 });
    });

    const form = document.querySelector('[data-newsletter]');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button');
        const email = form.email.value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { toast('Enter a valid email address.', 'error'); return; }
        btn.classList.add('is-loading');
        try {
            const d = await api('api/newsletter.php', { method: 'POST', body: { email } });
            toast(d.message);
            form.reset();
        } catch (err) { toast(err.message, 'error'); }
        finally { btn.classList.remove('is-loading'); }
    });
})();
