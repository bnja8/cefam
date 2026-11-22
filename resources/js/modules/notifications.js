export const initializeNotifications = () /* Callback que procesa el paso actual. */ => {
    const notification = document.querySelector('[data-notification-toast]');

    if (notification) {
        const dismissButton = notification.querySelector('[data-notification-dismiss]');
        let dismissTimer;

        const dismiss = () => {
            window.clearTimeout(dismissTimer);
            if (!notification.isConnected) return;
            notification.classList.add('is-dismissing');
            window.setTimeout(() /* Ejecuta la acción al finalizar la espera. */ => notification.remove(), 220);
        };

        const scheduleDismiss = () => {
            window.clearTimeout(dismissTimer);
            if (notification.dataset.notificationPersistent === 'true') return;
            dismissTimer = window.setTimeout(dismiss, 7000);
        };

        dismissButton?.addEventListener('click', dismiss);
        notification.addEventListener('mouseenter', ()  => window.clearTimeout(dismissTimer));
        notification.addEventListener('mouseleave', scheduleDismiss);
        notification.addEventListener('focusin', ()  => window.clearTimeout(dismissTimer));
        notification.addEventListener('focusout', (event)  => {
            if (!notification.contains(event.relatedTarget)) scheduleDismiss();
        });

        scheduleDismiss();
    }

    const menu = document.querySelector('[data-notification-menu]');
    if (!menu) return;

    const toggle = menu.querySelector('[data-notification-toggle]');
    const panel = menu.querySelector('[data-notification-panel]');
    const tabs = [...menu.querySelectorAll('[data-notification-tab]')];
    const items = [...menu.querySelectorAll('[data-notification-item]')];
    const emptyAll = menu.querySelector('[data-notification-empty]');
    const emptyUnread = menu.querySelector('[data-notification-filter-empty]');

    if (!toggle || !panel) return;

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
    };

    const setFilter = (filter) => {
        let visibleCount = 0;
        items.forEach((item) => {
            const visible = filter !== 'unread' || item.dataset.notificationUnread === 'true';
            item.hidden = !visible;
            if (visible) visibleCount += 1;
        });

        tabs.forEach((tab) => {
            const active = tab.dataset.notificationTab === filter;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-pressed', String(active));
        });

        if (emptyAll) emptyAll.hidden = filter === 'unread' || items.length > 0;
        if (emptyUnread) emptyUnread.hidden = filter !== 'unread' || visibleCount > 0;
    };

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    tabs.forEach((tab) => tab.addEventListener('click', () => setFilter(tab.dataset.notificationTab)));

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });
};
