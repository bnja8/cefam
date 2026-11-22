const states = new WeakMap();
const getState = (input) => {
    if (!states.has(input)) {
        states.set(input, { timer: null, controller: null, activeIndex: -1, products: [] });
    }

    return states.get(input);
};
const getControls = (input) => ({
    picker: input.closest('[data-product-picker]'),
    select: document.getElementById(input.dataset.productValueTarget),
    results: document.getElementById(input.getAttribute('aria-controls')),
});

const closeResults = (input) => {
    const results = document.getElementById(input.getAttribute('aria-controls'));
    if (!results) return;

    results.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    getState(input).activeIndex = -1;
};

const showMessage = (input, message) => {
    const results = document.getElementById(input.getAttribute('aria-controls'));
    if (!results) return;

    results.replaceChildren();
    const element = document.createElement('div');
    element.className = 'search-result-message';
    element.setAttribute('role', 'status');
    element.textContent = message;
    results.append(element);
    results.hidden = false;
    input.setAttribute('aria-expanded', 'true');
};

const selectedProductIds = (input) => {
    const list = input.closest('[data-distinct-products-list]');
    if (!list) return new Set();

    return new Set(
        [...list.querySelectorAll('[data-product-select]')]
            .map((select)  => select.value)
            .filter(Boolean)
    );
};
const formatStock = (value) => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(value);
const renderProducts = (input, products) => {
    const { results } = getControls(input);
    if (!results) return;

    const selected = selectedProductIds(input);
    const availableProducts = products.filter((product) /* Filtra los elementos de la colección. */ => !selected.has(String(product.producto_id)));
    results.replaceChildren();
    if (availableProducts.length === 0) {
        showMessage(input, products.length ? 'Los productos ya seleccionados no se repiten.' : 'No se encontraron productos activos.');
        return;
    }

    availableProducts.forEach((product, index)  => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'search-result product-search-result';
        option.id = `${input.id}-option-${index}`;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        option.dataset.productId = product.producto_id;

        const name = document.createElement('strong');
        name.textContent = product.nombre;
        option.append(name);
        if (input.dataset.productShowResultDetails !== 'false') {
            const details = document.createElement('small');
            const availability = Number(product.stock_disponible) > 0
                ? `Disponible en vitrina: ${formatStock(Number(product.stock_disponible))} unid.`
                : 'Sin stock vigente disponible en vitrina.';
            const locationDetails = Array.isArray(product.ubicaciones)
                ? (product.ubicaciones.length
                    ? product.ubicaciones.map((location)  =>
                        `${location.nombre}: ${formatStock(Number(location.cantidad))} ${location.unidad_base_codigo || 'unid.'}`)
                        .join(' · ')
                    : 'Sin existencias vigentes en ubicaciones.')
                : null;
            details.textContent = product.detalle || locationDetails || [
                product.unidad_base_codigo && `Unidad base: ${product.unidad_base_codigo}`,
                availability,
            ].filter(Boolean).join(' · ');
            option.append(details);
        }
        const select = getControls(input).select;
        const choose = () => {
            if (select?.value !== String(product.producto_id)) chooseProduct(input, product);
        };
        option.addEventListener('mousedown', (event)  => {
            event.preventDefault();
            choose();
        });
        option.addEventListener('click', choose);
        results.append(option);
    });

    results.hidden = false;
    input.setAttribute('aria-expanded', 'true');
};
const updateSelectedProduct = (select) => {
    const picker = select.closest('[data-product-picker]');
    const input = picker?.querySelector('[data-product-search-input]');
    const availability = picker?.querySelector('[data-product-availability]');
    const option = select.selectedOptions[0];
    if (!input) return;

    if (!select.value) {
        input.value = '';
        if (availability) availability.textContent = '';
        input.setCustomValidity(input.required ? 'Selecciona un medicamento de los resultados.' : '');
        return;
    }

    input.value = option.textContent.trim();
    input.setCustomValidity('');
    const stock = Number(option.dataset.stock);
    if (availability) {
        if (option.dataset.hasLocationDetails === 'true') {
            availability.textContent = option.dataset.locationDetails || 'Sin existencias vigentes en ubicaciones.';
            return;
        }
        const unit = option.dataset.unit ? `Unidad base: ${option.dataset.unit} · ` : '';
        availability.textContent = unit + (Number.isFinite(stock)
            ? (stock > 0
                ? `Disponible en vitrina: ${formatStock(stock)} unid.`
                : 'Sin stock vigente disponible en vitrina.')
            : 'La disponibilidad se confirmará según FEFO al registrar la entrega.');
    }
};

const chooseProduct = (input, product) => {
    const { select } = getControls(input);
    const availability = input.closest('[data-product-picker]')?.querySelector('[data-product-availability]');
    if (!select) return;

    if (selectedProductIds(input).has(String(product.producto_id))) {
        showMessage(input, 'Este producto ya está seleccionado en otra línea.');
        return;
    }

    const option = new Option(product.nombre, String(product.producto_id));
    option.dataset.controlled = String(Number(product.es_controlado));
    option.dataset.stock = Number.isFinite(Number(product.stock_disponible))
        ? String(Number(product.stock_disponible))
        : '';
    if (product.unidad_base_codigo) option.dataset.unit = product.unidad_base_codigo;
    if (product.via_administracion) option.dataset.route = product.via_administracion;
    if (Array.isArray(product.ubicaciones)) {
        option.dataset.hasLocationDetails = 'true';
        option.dataset.locationDetails = product.ubicaciones.map((location)  =>
            `${location.nombre}: ${formatStock(Number(location.cantidad))} ${location.unidad_base_codigo || 'unid.'}`)
            .join(' · ');
    }
    select.replaceChildren(new Option('Selecciona un producto', ''), option);
    select.value = option.value;
    input.value = product.nombre;
    input.setCustomValidity('');
    if (availability) {
        const unit = product.unidad_base_codigo ? `Unidad base: ${product.unidad_base_codigo} · ` : '';
        availability.textContent = unit + (Number(product.stock_disponible) > 0
            ? `Disponible en vitrina: ${formatStock(Number(product.stock_disponible))} unid.`
            : 'Sin stock vigente disponible en vitrina.');
    }
    closeResults(input);
    select.dispatchEvent(new Event('change', { bubbles: true }));
};
export const initializeProductSearch = () => {
    document.addEventListener('input', (event)  => {
        const input = event.target.closest('[data-product-search-input]');
        if (!input) return;

        const { select } = getControls(input);
        const state = getState(input);
        state.controller?.abort();
        window.clearTimeout(state.timer);

        if (select?.value) {
            select.value = '';
            select.dataset.productSearching = 'true';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            delete select.dataset.productSearching;
        }
        input.setCustomValidity(input.value.trim() ? 'Selecciona un medicamento de los resultados.' : '');

        const term = input.value.trim();
        if (term.length < 2) {
            closeResults(input);
            return;
        }

        state.timer = window.setTimeout(async () /* Ejecuta la acción al finalizar la espera. */ => {
            state.controller = new AbortController();
            showMessage(input, 'Buscando productos…');
            try {
                const url = new URL(input.dataset.productSearchUrl, window.location.origin);
                url.searchParams.set('q', term);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: state.controller.signal,
                });
                if (!response.ok) throw new Error('No se pudo completar la búsqueda de productos.');
                const products = await response.json();
                if (!input.isConnected || input.value.trim() !== term) return;
                state.products = products;
                renderProducts(input, products);
            } catch (error) {
                if (error.name === 'AbortError') return;
                console.error(error);
                showMessage(input, 'No se pudo buscar. Intenta nuevamente.');
            }
        }, 250);
    });

    document.addEventListener('change', (event)  => {
        const select = event.target.closest('[data-product-select]');
        if (!select || select.dataset.productSearching === 'true') return;

        updateSelectedProduct(select);
    });

    document.addEventListener('keydown', (event)  => {
        const input = event.target.closest('[data-product-search-input]');
        if (!input || !['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(event.key)) return;

        const results = document.getElementById(input.getAttribute('aria-controls'));
        const options = [...(results?.querySelectorAll('[role="option"]') || [])];
        const state = getState(input);
        if (event.key === 'Escape') {
            closeResults(input);
            return;
        }
        if (options.length === 0 || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
        if (event.key === 'Enter') {
            event.preventDefault();
            options[state.activeIndex >= 0 ? state.activeIndex : 0].click();
            return;
        }

        event.preventDefault();
        state.activeIndex = (state.activeIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
        options.forEach((option, index)  => option.setAttribute('aria-selected', String(index === state.activeIndex)));
        input.setAttribute('aria-activedescendant', options[state.activeIndex].id);
    });

    document.addEventListener('click', (event)  => {
        if (event.target.closest('[data-product-picker]')) return;
        document.querySelectorAll('[data-product-search-input]').forEach((input)  => {
            const state = getState(input);
            state.controller?.abort();
            window.clearTimeout(state.timer);
            closeResults(input);
        });
    });

    document.addEventListener('dispensation-products-changed', ()  => {
        document.querySelectorAll('[data-product-search-input]').forEach((input)  => {
            const state = getState(input);
            state.controller?.abort();
            window.clearTimeout(state.timer);
            closeResults(input);
        });
    });
};
