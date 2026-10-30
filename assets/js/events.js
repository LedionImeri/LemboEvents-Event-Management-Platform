/* Explore page: live search, filters, sorting and "load more" via fetch. */
(function () {
    'use strict';
    const { api, toast } = window.Lembo;
    const results = document.querySelector('[data-results]');
    if (!results) return;

    const qInput = document.getElementById('exploreQ');
    const filtersForm = document.querySelector('[data-filters-form]');
    const sortSel = document.querySelector('[data-sort]');
    const countEl = document.querySelector('[data-results-count]');
    const emptyEl = document.querySelector('[data-empty]');
    const moreBtn = document.querySelector('[data-load-more]');
    const activeEl = document.querySelector('[data-active-filters]');
    const panel = document.getElementById('filters');
    const catInput = filtersForm.querySelector('[name=category]');
    let controller = null;
    let page = Number(moreBtn.dataset.page || 1);

    const LABELS = { today: 'Today', tomorrow: 'Tomorrow', weekend: 'This weekend', week: 'Next 7 days', month: 'Next 30 days', free: 'Free', paid: 'Paid' };

    function params() {
        const p = new URLSearchParams();
        if (qInput.value.trim()) p.set('q', qInput.value.trim());
        new FormData(filtersForm).forEach((v, k) => { if (String(v).trim() !== '') p.set(k, String(v).trim()); });
        if (sortSel.value && sortSel.value !== 'date') p.set('sort', sortSel.value);
        return p;
    }

    function skeletons(n) {
        let h = '';
        for (let i = 0; i < n; i++) h += '<div class="skeleton-card" aria-hidden="true"><div class="sk sk-media"></div><div class="sk-body"><div class="sk" style="width:35%"></div><div class="sk" style="width:85%;height:18px"></div><div class="sk" style="width:60%"></div><div class="sk" style="width:45%"></div></div></div>';
        return h;
    }

    function renderActive(p) {
        activeEl.innerHTML = '';
        const add = (key, label) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.dataset.clear = key;
            b.textContent = label + ' ';
            b.insertAdjacentHTML('beforeend', '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>');
            b.setAttribute('aria-label', 'Remove filter ' + label);
            activeEl.appendChild(b);
        };
        if (p.get('q')) add('q', '“' + p.get('q') + '”');
        if (p.get('city')) add('city', p.get('city'));
        if (p.get('date')) add('date', LABELS[p.get('date')]);
        if (p.get('from') || p.get('to')) add('range', (p.get('from') || '…') + ' → ' + (p.get('to') || '…'));
        if (p.get('price')) add('price', LABELS[p.get('price')]);
        if (p.get('min') || p.get('max')) add('minmax', '€' + (p.get('min') || '0') + ' – ' + (p.get('max') ? '€' + p.get('max') : 'any'));
    }

    async function load(append = false) {
        const p = params();
        page = append ? page + 1 : 1;
        if (page > 1) p.set('page', page);
        if (controller) controller.abort();
        controller = new AbortController();

        if (!append) {
            results.innerHTML = skeletons(6);
            results.setAttribute('aria-busy', 'true');
            const urlParams = new URLSearchParams(p);
            urlParams.delete('page');
            history.replaceState(null, '', location.pathname + (urlParams.toString() ? '?' + urlParams : ''));
        } else {
            moreBtn.classList.add('is-loading');
        }
        renderActive(p);
        try {
            const data = await api('api/events.php?' + p.toString(), { signal: controller.signal });
            if (append) results.insertAdjacentHTML('beforeend', data.html);
            else results.innerHTML = data.html;
            countEl.textContent = data.total.toLocaleString() + (data.total === 1 ? ' event' : ' events') + ' found';
            emptyEl.hidden = data.total > 0;
            moreBtn.hidden = data.page >= data.pages;
        } catch (err) {
            if (err.name === 'AbortError') return;
            if (!append) results.innerHTML = '';
            toast(err.message, 'error');
        } finally {
            results.removeAttribute('aria-busy');
            moreBtn.classList.remove('is-loading');
        }
    }

    // Debounced search-as-you-type
    let t = null;
    qInput.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => load(), 320); });
    document.querySelector('[data-explore-search]').addEventListener('submit', (e) => { e.preventDefault(); clearTimeout(t); load(); });
    filtersForm.addEventListener('change', (e) => {
        if (e.target.name === 'date' && e.target.value) { filtersForm.from.value = ''; filtersForm.to.value = ''; }
        if ((e.target.name === 'from' || e.target.name === 'to') && e.target.value) {
            filtersForm.querySelector('[name=date][value=""]').checked = true;
        }
        load();
    });
    filtersForm.addEventListener('submit', (e) => e.preventDefault());
    sortSel.addEventListener('change', () => load());
    moreBtn.addEventListener('click', () => load(true));

    document.querySelectorAll('[data-cat]').forEach((chip) => chip.addEventListener('click', () => {
        document.querySelectorAll('[data-cat]').forEach((c) => { c.classList.remove('is-active'); c.setAttribute('aria-pressed', 'false'); });
        chip.classList.add('is-active');
        chip.setAttribute('aria-pressed', 'true');
        catInput.value = chip.dataset.cat;
        load();
    }));

    activeEl.addEventListener('click', (e) => {
        const b = e.target.closest('[data-clear]');
        if (!b) return;
        const k = b.dataset.clear;
        if (k === 'q') qInput.value = '';
        else if (k === 'range') { filtersForm.from.value = ''; filtersForm.to.value = ''; }
        else if (k === 'minmax') { filtersForm.min.value = ''; filtersForm.max.value = ''; }
        else if (k === 'city') filtersForm.city.value = '';
        else filtersForm.querySelector('[name="' + k + '"][value=""]').checked = true;
        load();
    });

    document.querySelectorAll('[data-filters-reset]').forEach((b) => b.addEventListener('click', () => {
        qInput.value = '';
        filtersForm.reset();
        filtersForm.querySelectorAll('input[type=date], input[type=number]').forEach((i) => { i.value = ''; });
        filtersForm.querySelectorAll('input[type=radio][value=""]').forEach((r) => { r.checked = true; });
        filtersForm.city.value = '';
        catInput.value = '';
        sortSel.value = 'date';
        document.querySelectorAll('[data-cat]').forEach((c) => { const on = c.dataset.cat === ''; c.classList.toggle('is-active', on); c.setAttribute('aria-pressed', String(on)); });
        load();
    }));

    // Mobile bottom-sheet filters
    const openFilters = () => { panel.classList.add('is-open'); document.body.classList.add('modal-open'); };
    const closeFilters = () => { panel.classList.remove('is-open'); document.body.classList.remove('modal-open'); };
    document.querySelector('[data-filters-open]')?.addEventListener('click', openFilters);
    document.querySelectorAll('[data-filters-close]').forEach((b) => b.addEventListener('click', closeFilters));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeFilters(); });

    renderActive(params());
})();
