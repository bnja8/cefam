export const initializeScrollableTables = () /* Callback que procesa el paso actual. */ => {
    const regions = [...document.querySelectorAll('.table-responsive')];
    const updateRegion = (region) => {
        const isScrollable = region.scrollWidth > region.clientWidth + 1;
        if (!isScrollable) {
            region.removeAttribute('tabindex');
            region.removeAttribute('role');
            region.removeAttribute('aria-label');
            return;
        }

        const table = region.querySelector('table');
        const caption = table?.querySelector('caption')?.textContent.trim();
        const heading = region.closest('.card')?.querySelector('.card-title')?.textContent.trim();

        region.tabIndex = 0;
        region.setAttribute('role', 'region');
        region.setAttribute('aria-label', caption || heading || 'Tabla desplazable horizontalmente');
    };

    regions.forEach(updateRegion);
    window.addEventListener('resize', ()  => regions.forEach(updateRegion), { passive: true });
};
