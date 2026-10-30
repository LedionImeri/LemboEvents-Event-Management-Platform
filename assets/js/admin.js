/* Admin: fill moderation modals with the clicked row's data. */
(function () {
    'use strict';
    const bind = (modalId, fill) => {
        const m = document.getElementById(modalId);
        m?.addEventListener('modal:open', (e) => fill(m, e.detail.trigger));
    };
    bind('rejectOrgModal', (m, t) => {
        m.querySelector('[name=org_id]').value = t.dataset.org;
        m.querySelector('[data-reject-name]').textContent = 'Organizer: ' + t.dataset.name;
        m.querySelector('textarea').value = '';
    });
    bind('rejectEventModal', (m, t) => {
        m.querySelector('[name=event_id]').value = t.dataset.event;
        m.querySelector('[data-reject-name]').textContent = 'Event: ' + t.dataset.name;
        m.querySelector('textarea').value = '';
    });
    bind('resolveReportModal', (m, t) => {
        m.querySelector('[name=report_id]').value = t.dataset.report;
        m.querySelector('[name=action]').value = t.dataset.action;
        m.querySelector('h2').textContent = t.dataset.action === 'resolve' ? 'Resolve report' : 'Dismiss report';
        m.querySelector('[data-hide-wrap]').hidden = t.dataset.canHide !== '1' || t.dataset.action !== 'resolve';
    });
    bind('categoryModal', (m, t) => {
        const f = m.querySelector('form');
        f.reset();
        const data = t.dataset.category ? JSON.parse(t.dataset.category) : null;
        m.querySelector('h2').textContent = data ? 'Edit category' : 'New category';
        f.category_id.value = data ? data.id : '';
        f.name.value = data ? data.name : '';
        f.description.value = data ? (data.description || '') : '';
        f.color.value = data ? data.color : '#FF4D2E';
        f.icon.value = data ? data.icon : 'spark';
        f.sort_order.value = data ? data.sort_order : 0;
        f.is_active.checked = data ? data.is_active === 1 : true;
    });
})();
