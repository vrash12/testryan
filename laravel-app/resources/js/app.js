import { initializeUploads } from './uploads';

initializeUploads();

const inboxToggle = document.querySelector('[data-inbox-select-toggle]');
if (inboxToggle) {
    const boxes = [...document.querySelectorAll('[data-inbox-item]')];
    const count = document.querySelector('[data-inbox-selection-count]');
    const updateSelection = () => {
        const selected = boxes.filter((box) => box.checked).length;
        count.textContent = `${selected} selected on this page.`;
        inboxToggle.textContent = selected === boxes.length ? 'Clear selection' : 'Select this page';
    };
    inboxToggle.hidden = false;
    count.hidden = false;
    inboxToggle.addEventListener('click', () => {
        const select = !boxes.every((box) => box.checked);
        boxes.forEach((box) => { box.checked = select; });
        updateSelection();
    });
    boxes.forEach((box) => box.addEventListener('change', updateSelection));
    window.addEventListener('pageshow', updateSelection);
    updateSelection();
}

// Native <details> keeps mobile navigation usable without JavaScript.
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    const label = button.getAttribute('aria-label').slice(5);
    button.hidden = false;
    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Hide' : 'Show';
        button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${label}`);
        button.setAttribute('aria-pressed', String(visible));
    });
});

document.querySelectorAll('form[method="POST"]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || form.dataset.asyncReady === 'true') return;
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = false; });
    });
});

document.querySelectorAll('[data-mobile-menu]').forEach((button) => {
    const menu = document.getElementById('mobile-navigation');
    if (!menu) return;
    menu.addEventListener('toggle', () => {
        button.setAttribute('aria-expanded', String(menu.open));
    });
    button.addEventListener('click', () => {
        menu.open = !menu.open;
        if (menu.open) menu.querySelector('summary').focus();
    });
});

const serviceFilter = document.querySelector('[data-service-filter]');
const serviceOptions = document.querySelector('[data-service-options]');
const serviceCount = document.querySelector('[data-service-count]');
if (serviceFilter && serviceOptions && serviceCount) {
    const update = () => {
        const term = serviceFilter.value.trim().toLocaleLowerCase();
        const labels = [...serviceOptions.querySelectorAll('[data-service-name]')];
        labels.forEach((label) => { label.hidden = !label.dataset.serviceName.toLocaleLowerCase().includes(term); });
        const selected = serviceOptions.querySelectorAll('input:checked').length;
        serviceCount.textContent = `${selected} service${selected === 1 ? '' : 's'} selected`;
    };
    serviceFilter.addEventListener('input', update);
    serviceOptions.addEventListener('change', update);
    update();
}

// Persist only the navigation display preference, never account data.
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
if (sidebarToggle) {
    const applySidebarState = (collapsed) => {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
        sidebarToggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
    };
    try { applySidebarState(localStorage.getItem('nestcrafters.sidebar') === 'collapsed'); } catch { /* Storage may be unavailable. */ }
    sidebarToggle.addEventListener('click', () => {
        const collapsed = !document.body.classList.contains('sidebar-collapsed');
        applySidebarState(collapsed);
        try { localStorage.setItem('nestcrafters.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch { /* Toggle works without persistence. */ }
    });
}
const mobileNavigation = document.getElementById('mobile-navigation');
mobileNavigation?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && mobileNavigation.open) {
        mobileNavigation.open = false;
        mobileNavigation.querySelector('summary').focus();
    }
});
