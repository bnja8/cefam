const SEARCH_DELAY = 450;

const replaceMatchingNodes = (currentSection, nextSection, selector) => {
    const currentNodes = [...currentSection.querySelectorAll(selector)];
    const nextNodes = [...nextSection.querySelectorAll(selector)];

    currentNodes.forEach((currentNode, index) => {
        const nextNode = nextNodes[index];
        if (!nextNode) return;
        currentNode.replaceWith(nextNode.cloneNode(true));
    });
};

const updateSectionResults = (form, input, html) => {
    const parser = new DOMParser();
    const nextDocument = parser.parseFromString(html, 'text/html');
    const currentSection = form.closest('section');
    if (!currentSection) return;

    let nextSection = null;
    if (currentSection.id) {
        nextSection = nextDocument.getElementById(currentSection.id);
    }
    if (!nextSection) {
        const formAction = new URL(form.action, window.location.origin).pathname;
        nextSection = [...nextDocument.querySelectorAll('section')]
            .find((section) => section.querySelector(`form[action="${formAction}"]`) || section.querySelector('form'));
    }
    if (!nextSection) return;

    replaceMatchingNodes(currentSection, nextSection, '.searchable-cards');
    replaceMatchingNodes(currentSection, nextSection, '.table-responsive');
    replaceMatchingNodes(currentSection, nextSection, '.record-pagination');

    const currentHeader = currentSection.querySelector('.card-header');
    const nextHeader = nextSection.querySelector('.card-header');
    if (currentHeader && nextHeader) {
        const currentMuted = currentHeader.querySelector('.muted');
        const nextMuted = nextHeader.querySelector('.muted');
        if (currentMuted && nextMuted) currentMuted.replaceWith(nextMuted.cloneNode(true));
    }

    input.focus({ preventScroll: true });
};

export const initializeLiveRecordSearch = () => {
    const timers = new WeakMap();
    const controllers = new WeakMap();
    const searchInputSelector = 'input[name*="buscar"]';

    document.addEventListener('input', (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.matches(searchInputSelector)) return;

        const form = input.form;
        if (!form || form.method.toLowerCase() !== 'get') return;

        const previousTimer = timers.get(form);
        if (previousTimer !== undefined) window.clearTimeout(previousTimer);

        timers.set(form, window.setTimeout(async () => {
            const previousController = controllers.get(form);
            if (previousController) previousController.abort();

            const controller = new AbortController();
            controllers.set(form, controller);
            const url = new URL(form.action, window.location.origin);
            new URLSearchParams(new FormData(form)).forEach((value, key) => url.searchParams.set(key, value));

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error(`Búsqueda no disponible (${response.status})`);

                const html = await response.text();
                updateSectionResults(form, input, html);
                window.history.replaceState({}, '', `${url.pathname}${url.search}${window.location.hash}`);
            } catch (error) {
                if (error.name !== 'AbortError') console.error('No se pudo actualizar la búsqueda.', error);
            }
        }, SEARCH_DELAY));
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.querySelector(searchInputSelector)) return;

        event.preventDefault();
        const input = form.querySelector(searchInputSelector);
        if (input) input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('.record-pagination a');
        if (!link) return;

        event.preventDefault();
        const section = link.closest('section');
        const form = section?.querySelector('form[method="get"]');
        if (!form) {
            window.location.assign(link.href);
            return;
        }

        const url = new URL(link.href, window.location.origin);
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        }).then((response) => {
            if (!response.ok) throw new Error(`Paginación no disponible (${response.status})`);
            return response.text();
        }).then((html) => {
            const input = form.querySelector(searchInputSelector);
            if (input) updateSectionResults(form, input, html);
            window.history.replaceState({}, '', `${url.pathname}${url.search}${window.location.hash}`);
        }).catch((error) => console.error('No se pudo cargar la página solicitada.', error));
    });
};
