import { formatQuantity } from './format';
export const initializeInventoryCatalog = () => {
    const inventoryTable = document.querySelector('[data-inventory-api]');
    const inventoryRows = document.querySelector('#inventory-product-rows');
    const inventoryStatus = document.querySelector('[data-inventory-status]');
    const inventorySearch = document.querySelector('#inventory-search');
    const inventoryCount = document.querySelector('[data-inventory-count]');
    const pagination = document.querySelector('[data-inventory-pagination]');
    const pageLabel = document.querySelector('[data-inventory-page-label]');
    const previousButton = document.querySelector('[data-inventory-previous]');
    const nextButton = document.querySelector('[data-inventory-next]');
    if (!inventoryTable || !inventoryRows || !inventoryStatus || !pagination) return;

    let timer;
    let controller;
    const renderProduct = (product) => {
        const row = document.createElement('tr');
        const nameCell = document.createElement('td');
        const link = document.createElement('a');
        link.className = 'text-link';
        link.href = product.url;
        const name = document.createElement('strong');
        name.textContent = `${product.codigo} · ${product.nombre}`;
        link.append(name);
        const description = document.createElement('small');
        description.className = 'table-subtitle';
        description.textContent = `${product.cantidad_lotes} ${product.cantidad_lotes === 1 ? 'lote' : 'lotes'} · Unidad base: ${product.unidad_base || 'sin definir'} · Ver lotes y ubicaciones`;
        nameCell.append(link, description);

        const totalCell = document.createElement('td');
        const total = document.createElement('strong');
        total.textContent = formatQuantity(product.stock_fisico);
        totalCell.append(total);

        const availableCell = document.createElement('td');
        const availableQuantity = document.createElement('strong');
        availableQuantity.textContent = formatQuantity(product.stock_total);
        availableCell.append(availableQuantity);
        const expiredCell = document.createElement('td');
        expiredCell.textContent = formatQuantity(product.stock_vencido);
        const blockedCell = document.createElement('td');
        blockedCell.textContent = formatQuantity(product.stock_bloqueado);
        const upcomingLotCell = document.createElement('td');
        if (product.proximo_lote && product.proximo_vencimiento) {
            const lotNumber = document.createElement('strong');
            lotNumber.textContent = product.proximo_lote;
            const expiryDate = document.createElement('small');
            expiryDate.className = 'table-subtitle';
            const [year, month, day] = product.proximo_vencimiento.split('-');
            expiryDate.textContent = `${day}/${month}/${year}`;
            upcomingLotCell.append(lotNumber, expiryDate);
        } else {
            upcomingLotCell.textContent = '—';
        }
        const lotsCell = document.createElement('td');
        const lotsCount = document.createElement('span');
        lotsCount.className = 'inventory-count-pill';
        lotsCount.textContent = product.cantidad_lotes;
        lotsCell.append(lotsCount);
        const stateCell = document.createElement('td');
        const badge = document.createElement('span');
        const available = product.estado === 'DISPONIBLE';
        badge.className = `badge ${available ? 'badge-success' : product.estado === 'PRODUCTO_INACTIVO' ? 'badge-danger' : 'badge-warning'}`;
        badge.textContent = available ? 'Disponible' : product.estado === 'PRODUCTO_INACTIVO' ? 'Producto inactivo' : 'Sin stock vigente';
        stateCell.append(badge);

        const actionCell = document.createElement('td');
        const lotsLink = document.createElement('a');
        lotsLink.className = 'btn btn-outline btn-small';
        lotsLink.href = `${product.url}#lotes-producto`;
        lotsLink.textContent = 'Ver lotes';
        actionCell.append(lotsLink);
        row.append(nameCell, totalCell, availableCell, expiredCell, blockedCell, upcomingLotCell, lotsCell, stateCell, actionCell);
        return row;
    };
    const loadInventoryPage = async (page = 1) => {
        controller?.abort();
        controller = new AbortController();
        inventoryStatus.textContent = 'Cargando inventario…';
        inventoryStatus.classList.remove('text-danger');
        try {
            const url = new URL(inventoryTable.dataset.inventoryApi, window.location.origin);
            const term = inventorySearch?.value.trim() ?? '';
            if (term) url.searchParams.set('buscar', term);
            url.searchParams.set('page', String(page));
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'No se pudo cargar el inventario agrupado.');
            if (controller.signal.aborted) return;

            if (result.data.length === 0) {
                const empty = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 9;
                cell.className = 'empty-state';
                cell.textContent = 'No se encontraron medicamentos o insumos.';
                empty.append(cell);
                inventoryRows.replaceChildren(empty);
            } else {
                inventoryRows.replaceChildren(...result.data.map(renderProduct));
            }

            pagination.dataset.currentPage = String(result.current_page);
            pagination.dataset.lastPage = String(result.last_page);
            if (previousButton) previousButton.disabled = result.current_page <= 1;
            if (nextButton) nextButton.disabled = result.current_page >= result.last_page;
            if (pageLabel) pageLabel.textContent = `Página ${result.current_page} de ${result.last_page}`;
            if (inventoryCount) {
                inventoryCount.textContent = result.total > 0
                    ? `Mostrando ${result.from}–${result.to} de ${result.total} productos`
                    : '0 productos';
            }
            inventoryStatus.textContent = '';
        } catch (error) {
            if (error.name === 'AbortError') return;
            inventoryStatus.textContent = error instanceof Error ? error.message : 'No se pudo cargar el inventario agrupado.';
            inventoryStatus.classList.add('text-danger');
        }
    };

    const scheduleSearch = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() /* Ejecuta la acción al finalizar la espera. */ => loadInventoryPage(1), 250);
    };
    inventorySearch?.addEventListener('input', scheduleSearch);
    previousButton?.addEventListener('click', ()  => {
        loadInventoryPage(Number(pagination.dataset.currentPage) - 1);
    });
    nextButton?.addEventListener('click', ()  => {
        loadInventoryPage(Number(pagination.dataset.currentPage) + 1);
    });
};
