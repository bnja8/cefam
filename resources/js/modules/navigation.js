// Controla la navegación lateral y sus estados.
export const initializeSidebar = ()  => {
    const sidebar = document.querySelector('#sidebar');
    const toggle = document.querySelector('#sidebar-toggle');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!sidebar || !toggle || !backdrop) return;

    const sidebarNavigation = sidebar.querySelector('#sidebar-nav');
    const sidebarScrollStorageKey = 'farmacia-sidebar-scroll-top';
    if (sidebarNavigation) {
        try {
            const savedScrollTop = window.sessionStorage.getItem(sidebarScrollStorageKey);
            if (savedScrollTop !== null && Number.isFinite(Number(savedScrollTop))) {
                window.requestAnimationFrame(() => {
                    sidebarNavigation.scrollTop = Number(savedScrollTop);
                });
            }
        } catch (error) {
            console.error('No se pudo restaurar la posición del menú lateral.', error);
        }
    }

    const saveSidebarScrollPosition = () => {
        if (!sidebarNavigation) return;
        try {
            window.sessionStorage.setItem(sidebarScrollStorageKey, String(sidebarNavigation.scrollTop));
        } catch (error) {
            console.error('No se pudo guardar la posición del menú lateral.', error);
        }
    };

    const moduleSearch = sidebar.querySelector('[data-sidebar-module-search]');
    const moduleSearchEmpty = sidebar.querySelector('[data-sidebar-search-empty]');
    const moduleGroups = [...sidebar.querySelectorAll('.nav-group')];
    if (moduleSearch) {
        const normalizeSearchText = (value) => value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase();
        const filterModules = () => {
            const query = normalizeSearchText(moduleSearch.value.trim());
            let visibleModules = 0;
            moduleGroups.forEach((group) => {
                const links = [...group.querySelectorAll('.nav-item')];
                let visibleInGroup = 0;
                links.forEach((link) => {
                    const searchableText = link.dataset.searchText || link.textContent;
                    const matches = !query || normalizeSearchText(searchableText).includes(query);
                    link.hidden = !matches;
                    if (matches) visibleInGroup += 1;
                });
                group.hidden = visibleInGroup === 0;
                visibleModules += visibleInGroup;
            });
            if (moduleSearchEmpty) moduleSearchEmpty.hidden = !query || visibleModules > 0;
        };

        moduleSearch.addEventListener('input', filterModules);
        moduleSearch.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !moduleSearch.value) return;
            event.preventDefault();
            moduleSearch.value = '';
            filterModules();
        });
    }

    const mobileQuery = window.matchMedia('(max-width: 760px)');
    // Abre o cierra el menú lateral en pantallas pequeñas.
    const setMobileMenuOpen = (open) => {
        sidebar.classList.toggle('mobile-open', open);
        sidebar.inert = mobileQuery.matches && !open;
        backdrop.hidden = !open;
        document.body.classList.toggle('mobile-menu-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Cerrar navegación' : 'Mostrar navegación');
    };

    // Sincroniza el estado del menú lateral en escritorio.
    const syncDesktopMenuState = () => {
        const expanded = !sidebar.classList.contains('collapsed');
        sidebar.inert = !expanded;
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', expanded ? 'Ocultar navegación' : 'Mostrar navegación');
    };

    // Ajusta la navegación según el tamaño de pantalla.
    const syncMenuForViewport = () => {
        if (mobileQuery.matches) {
            sidebar.classList.remove('collapsed');
            setMobileMenuOpen(false);
            return;
        }
        setMobileMenuOpen(false);
        syncDesktopMenuState();
    };

    toggle.addEventListener('click', ()  => {
        if (mobileQuery.matches) {
            setMobileMenuOpen(!sidebar.classList.contains('mobile-open'));
        } else {
            sidebar.classList.toggle('collapsed');
            syncDesktopMenuState();
        }
    });

    backdrop.addEventListener('click', ()  => setMobileMenuOpen(false));
    sidebar.querySelectorAll('.nav-item, .sidebar-brand-link').forEach((link)  => {
        link.addEventListener('click', ()  => {
            saveSidebarScrollPosition();
            if (mobileQuery.matches) setMobileMenuOpen(false);
        });
    });
    document.addEventListener('keydown', (event)  => {
        if (event.key === 'Escape' && mobileQuery.matches && sidebar.classList.contains('mobile-open')) {
            setMobileMenuOpen(false);
            toggle.focus();
        }
    });
    mobileQuery.addEventListener('change', syncMenuForViewport);
    syncMenuForViewport();
};
