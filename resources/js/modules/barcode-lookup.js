const resolveBarcode = async (target, barcode) /* Callback que procesa el paso actual. */ => {
    const productSelect = target.matches('[data-product-select]')
        ? target
        : target.closest('[data-product-picker]')?.querySelector('[data-product-select]');
    if (!productSelect) return;
    const status = productSelect.closest('[data-dispensation-row]')?.querySelector('[data-product-status]');
    if (!status) return;

    status.hidden = false;
    status.textContent = 'Buscando el producto asociado al código...';

    try {
        const url = new URL(productSelect.dataset.barcodeUrl, window.location.origin);
        url.searchParams.set('codigo_barra', barcode);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const result = await response.json();

        if (!response.ok) throw new Error(result.message || 'No se pudo consultar el código.');

        let option = [...productSelect.options].find((item)  => item.value === String(result.producto_id));
        const alreadySelected = [...productSelect.closest('[data-distinct-products-list]')?.querySelectorAll('[data-product-select]') || []]
            .some((select)  => select !== productSelect && select.value === String(result.producto_id));
        if (result.es_controlado) {
            status.textContent = `${result.nombre} requiere autorización del Químico Farmacéutico.`;
        } else if (alreadySelected || (option?.disabled && productSelect.value !== option.value)) {
            status.textContent = `${result.nombre} ya fue seleccionado en otra línea.`;
        } else {
            if (!option) {
                productSelect.replaceChildren(new Option('Selecciona un producto', ''));
                option = new Option(result.nombre, String(result.producto_id));
                option.dataset.controlled = String(Number(result.es_controlado));
                productSelect.add(option);
            }
            productSelect.value = option.value;
            productSelect.dispatchEvent(new Event('change', { bubbles: true }));
            status.textContent = result.lote_fefo
                ? `Producto seleccionado: ${result.nombre}.`
                : `${result.nombre} seleccionado; no hay stock vigente disponible en vitrina.`;
        }
    } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'Ocurrió un error al buscar el código.';
    }
};
const refreshFefoPreview = async (productSelect) => {
    const row = productSelect.closest('[data-dispensation-row]');
    const summary = row?.querySelector('[data-prescription-fefo]');
    const scannerStatus = row?.querySelector('[data-product-status]');
    const status = summary || scannerStatus;
    const quantityInput = row?.querySelector('input[name$="[cantidad]"]');
    const productId = productSelect.value;
    const requestId = String(Number(productSelect.dataset.fefoRequest || 0) + 1);
    productSelect.dataset.fefoRequest = requestId;

    if (!productId) {
        [summary, scannerStatus].forEach((element) => {
            if (!element) return;
            element.hidden = true;
            element.replaceChildren();
            delete element.dataset.state;
        });
        return;
    }
    if (!productSelect.dataset.fefoUrl || !status) return;

    status.hidden = false;
    status.dataset.state = 'loading';
    status.textContent = 'Consultando lotes vigentes en vitrina según FEFO…';
    try {
        const url = new URL(productSelect.dataset.fefoUrl, window.location.origin);
        url.pathname = url.pathname.replace(/\/0\/fefo$/, `/${productId}/fefo`);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (requestId !== productSelect.dataset.fefoRequest) return;
        if (!response.ok) throw new Error(result.message || 'No se pudo consultar la disponibilidad FEFO.');

        const requested = Number(quantityInput?.value || 0);
        let remaining = requested;
        const lots = Array.isArray(result.lotes) ? result.lotes : [];
        const availability = lots
            .map((lot)  => `(vence ${formatDate(lot.fecha_vencimiento)}; ${formatQuantity(lot.cantidad_disponible)} disp.)`)
            .join(' ');
        const allocations = [];
        lots.forEach((lot)  => {
            const available = Number(lot.cantidad_disponible);
            const assigned = requested > 0 ? Math.min(remaining, available) : 0;
            remaining = Math.max(0, remaining - assigned);
            if (assigned > 0 || (requested <= 0 && allocations.length === 0)) {
                allocations.push({ ...lot, cantidad_disponible: available, cantidad_asignada: assigned });
            }
        });

        if (summary) {
            renderFefoSummary(summary, allocations, requested, remaining);
        } else if (lots.length === 0) {
            status.textContent = 'Sin stock vigente disponible en vitrina.';
        } else if (requested <= 0) {
            status.textContent = availability;
        } else {
            const plan = allocations.map((lot) =>
                `${lot.numero_lote} (vence ${formatDate(lot.fecha_vencimiento)}; ${formatQuantity(lot.cantidad_disponible)} disp.) → ${formatQuantity(lot.cantidad_asignada)}`
            );
            const allocation = remaining > 0 ? ` Stock insuficiente: faltan ${formatQuantity(remaining)}.` : '';
            status.textContent = `FEFO: ${plan.join(' → ')}.${allocation}`;
        }
        status.dataset.state = remaining > 0 ? 'warning' : 'success';
    } catch (error) {
        if (requestId === productSelect.dataset.fefoRequest) {
            status.dataset.state = 'error';
            status.textContent = error instanceof Error ? error.message : 'No se pudo consultar la disponibilidad FEFO.';
        }
    }
};
const renderFefoSummary = (summary, allocations, requested, remaining) => {
    summary.replaceChildren();
    summary.dataset.state = remaining > 0 ? 'warning' : 'success';
    if (allocations.length === 0) {
        summary.dataset.state = 'empty';
        summary.textContent = 'Sin stock vigente disponible en vitrina.';
        return;
    }

    allocations.forEach((lot)  => {
        const badge = document.createElement('span');
        badge.className = 'dispensation-fefo-lot';
        const location = lot.ubicacion ? ` · ${lot.ubicacion}` : '';
        const text = requested > 0
            ? `Lote FEFO: ${lot.numero_lote}${location} · Vence ${formatDate(lot.fecha_vencimiento)} · Stock: ${formatQuantity(lot.cantidad_disponible)}}`
            : `Lote FEFO: ${lot.numero_lote}${location} · Vence ${formatDate(lot.fecha_vencimiento)} · Stock: ${formatQuantity(lot.cantidad_disponible)}`;
        badge.textContent = text;
        summary.append(badge);
    });

    if (requested <= 0) {
        const hint = document.createElement('span');
        hint.className = 'dispensation-fefo-hint';
        hint.textContent = 'Ingresa una cantidad para calcular la asignación.';
        summary.append(hint);
    } else if (remaining > 0) {
        const warning = document.createElement('span');
        warning.className = 'dispensation-fefo-warning';
        warning.textContent = `Stock insuficiente: faltan ${formatQuantity(remaining)}.`;
        summary.append(warning);
    }
};
const formatQuantity = (value) => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(value);
const formatDate = (value) => {
    const [year, month, day] = value.split('-').map(Number);
    return new Intl.DateTimeFormat('es-CL').format(new Date(year, month - 1, day));
};
export const initializeBarcodeLookup = () => {
    document.addEventListener('change', (event)  => {
        const select = event.target.closest('[data-product-select]');
        if (select) refreshFefoPreview(select);
    });
    document.addEventListener('input', (event)  => {
        const quantityInput = event.target.closest('[data-dispensation-row] input[name$="[cantidad]"]');
        const select = quantityInput?.closest('[data-dispensation-row]')?.querySelector('[data-product-select]');
        if (select?.value) refreshFefoPreview(select);
    });
};
export const initializeBarcodeScanner = () => {
    let scannerBuffer = '';
    let lastScannerKeyAt = 0;
    let scannerSelect = null;
    let rapidScan = true;
    const indicators = [...document.querySelectorAll('[data-barcode-scanner-test]')];
    let indicatorBuffer = '';
    let lastIndicatorKeyAt = 0;
    let indicatorCompletionTimeout;
    let indicatorResetTimeout;
    let indicatorDetected = false;
    const setIndicatorState = (state, message) => {
        indicators.forEach((indicator)  => {
            indicator.dataset.scannerState = state;
            const status = indicator.querySelector('[data-scanner-test-status]');
            if (status) status.textContent = message;
        });
    };

    const markReading = () => {
        window.clearTimeout(indicatorCompletionTimeout);
        window.clearTimeout(indicatorResetTimeout);
        indicatorBuffer = '';
        lastIndicatorKeyAt = 0;
        indicatorDetected = true;
        setIndicatorState('reading', 'Detectada la lectura');
        indicatorResetTimeout = window.setTimeout(() /* Ejecuta la acción al finalizar la espera. */ => {
            setIndicatorState('detected', 'Detectado pero sin lectura');
        }, 2500);
    };

    if (indicators.length > 0) {
        document.addEventListener('keydown', (event)  => {
            if (event.repeat) return;
            if (typeof event.key !== 'string') return;

            const now = performance.now();
            const gap = now - lastIndicatorKeyAt;
            const isTerminator = event.key === 'Enter' || event.key === 'Tab';

            if (isTerminator) {
                if (indicatorBuffer.length >= 4 && gap <= 120) {
                    if (event.key === 'Enter') event.preventDefault();
                    markReading();
                }
                return;
            }

            if (event.key.length !== 1) return;

            if (gap > 120 || indicatorBuffer.length === 0) {
                indicatorBuffer = event.key;
            } else if (gap <= 80) {
                indicatorBuffer += event.key;
            } else {
                indicatorBuffer = event.key;
            }
            lastIndicatorKeyAt = now;

            if (indicatorBuffer.length >= 2 && gap <= 80) {
                indicatorDetected = true;
                window.clearTimeout(indicatorResetTimeout);
                setIndicatorState('detected', 'Detectado pero sin lectura');
            }

            window.clearTimeout(indicatorCompletionTimeout);
            if (indicatorDetected && indicatorBuffer.length >= 4 && gap <= 80) {
                indicatorCompletionTimeout = window.setTimeout(markReading, 150);
            }
        });
    }

    document.addEventListener('keydown', (event)  => {
        const target = event.target;
        const isProductSelect = target instanceof HTMLSelectElement && target.matches('[data-product-select]');
        const isProductSearch = target instanceof HTMLInputElement && target.matches('[data-product-search-input]');
        if (!isProductSelect && !isProductSearch) {
            scannerBuffer = '';
            scannerSelect = null;
            return;
        }
        if (typeof event.key !== 'string') return;

        const now = performance.now();
        if (now - lastScannerKeyAt > 120 || scannerSelect !== target) {
            scannerBuffer = '';
            rapidScan = true;
            scannerSelect = target;
        } else if (now - lastScannerKeyAt > 80) {
            rapidScan = false;
        }
        lastScannerKeyAt = now;

        if (event.key === 'Enter') {
            const barcode = scannerBuffer;
            scannerBuffer = '';
            if (barcode.length >= 4 && rapidScan) {
                event.preventDefault();
                event.stopPropagation();
                if (isProductSearch) {
                    target.value = '';
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                }
                resolveBarcode(target, barcode);
            }
            return;
        }
        if (event.key.length === 1 && scannerBuffer.length < 100) scannerBuffer += event.key;
    });
};
