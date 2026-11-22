import { PENDING_SECTION_ID_KEY, PENDING_SECTION_PATH_KEY } from './form-section-state';

export const initializeFormSectionRestore = () => {
    document.addEventListener('submit', (event)  => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() === 'GET' || event.defaultPrevented) return;

        const section = form.closest('.card[id]');
        const sectionId = section?.id || window.location.hash.slice(1);
        if (!sectionId) return;

        try {
            sessionStorage.setItem(PENDING_SECTION_PATH_KEY, window.location.pathname);
            sessionStorage.setItem(PENDING_SECTION_ID_KEY, sectionId);
        } catch (error) {
            console.error('No se pudo guardar la sección del formulario.', error);
        }
    });
};
