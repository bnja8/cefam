import { PENDING_SECTION_ID_KEY, PENDING_SECTION_PATH_KEY } from './form-section-state';

let focusedViewVisibility = [];

const restoreFocusedView = () => {
    focusedViewVisibility.forEach(([element, wasHidden])  => {
        element.hidden = wasHidden;
    });
    focusedViewVisibility = [];
};

const restoreSubmittedSection = () => {
    try {
        const pendingPath = sessionStorage.getItem(PENDING_SECTION_PATH_KEY);
        const pendingSectionId = sessionStorage.getItem(PENDING_SECTION_ID_KEY);
        sessionStorage.removeItem(PENDING_SECTION_PATH_KEY);
        sessionStorage.removeItem(PENDING_SECTION_ID_KEY);
        if (pendingPath === window.location.pathname
            && pendingSectionId
            && document.getElementById(pendingSectionId)) {
            window.history.replaceState(
                null,
                '',
                `${window.location.pathname}${window.location.search}#${encodeURIComponent(pendingSectionId)}`,
            );
        }
    } catch (error) {
        console.error('No se pudo restaurar la sección del formulario.', error);
    }
};

const focusModuleSection = () => {
    restoreFocusedView();
    if (!window.location.hash) return;

    const targetId = window.location.hash.slice(1);
    const card = document.getElementById(targetId)?.closest('.card');
    const content = document.querySelector('.content-body');
    if (!card || !content) return;

    const cardGroup = card.parentElement?.classList.contains('dashboard-grid') ? card.parentElement : null;
    const visibleContent = new Set([
        card,
        cardGroup,
        ...[...content.children].filter((child) /* Filtra los elementos de la colección. */ => child.matches('.module-breadcrumb, .page-header, .flash, .notification-toast, dialog')),
    ]);

    [...content.children].forEach((child)  => {
        if (visibleContent.has(child)) return;
        focusedViewVisibility.push([child, child.hidden]);
        child.hidden = true;
    });

    if (cardGroup) {
        [...cardGroup.children].forEach((child)  => {
            if (child === card) return;
            focusedViewVisibility.push([child, child.hidden]);
            child.hidden = true;
        });
    }
};
export const initializeModuleSections = () => {
    restoreSubmittedSection();
    window.addEventListener('hashchange', focusModuleSection);
    focusModuleSection();
};
