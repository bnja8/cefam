export const initializeDeliveryOrderPrint = () => {
    document.addEventListener('click', (event) => {
        const printButton = event.target.closest('[data-page-print]');
        if (printButton) {
            window.print();
            return;
        }

        if (event.target.closest('[data-window-close]')) window.close();
    });

    const openResultDialogs = () => {
        document.querySelectorAll('[data-dispensation-result-dialog][data-open-on-load="true"]').forEach((dialog) => {
            const clearReceiptQuery = () => {
                const url = new URL(window.location.href);
                if (!url.searchParams.has('comprobante')) return;
                url.searchParams.delete('comprobante');
                window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
            };
            try {
                if (dialog.open) dialog.close();
                if (typeof dialog.showModal !== 'function') {
                    throw new Error('Este navegador no admite ventanas de diálogo modales.');
                }
                dialog.showModal();
                dialog.addEventListener('close', clearReceiptQuery, { once: true });
            } catch (error) {
                console.error('No se pudo abrir el comprobante de dispensación.', error);
                dialog.setAttribute('open', '');
                dialog.dataset.modalFallback = 'true';
                document.body.classList.add('delivery-order-dialog-fallback-open');
                dialog.addEventListener('close', clearReceiptQuery, { once: true });
                dialog.addEventListener('close', () => {
                    delete dialog.dataset.modalFallback;
                    document.body.classList.remove('delivery-order-dialog-fallback-open');
                }, { once: true });
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openResultDialogs, { once: true });
    } else {
        openResultDialogs();
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-print-label-url], [data-print-order-url], [data-print-prescription-url]');
        if (!button) return;

        const printOrder = button.hasAttribute('data-print-order-url');
        const printPrescription = button.hasAttribute('data-print-prescription-url');
        const expectedBodyClass = printOrder
            ? 'print-delivery-order'
            : printPrescription
                ? 'print-prescription'
                : 'print-delivery-label';
        const printUrl = button.getAttribute(printOrder
            ? 'data-print-order-url'
            : printPrescription
                ? 'data-print-prescription-url'
                : 'data-print-label-url');
        const status = document.querySelector('[data-print-status]');
        const frame = document.createElement('iframe');
        frame.title = printPrescription
            ? 'Receta médica para imprimir'
            : printOrder
                ? 'Orden de dispensación para imprimir'
                : 'Etiqueta para imprimir';
        frame.setAttribute('aria-hidden', 'true');
        frame.tabIndex = -1;
        frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1000px;height:1000px;border:0';
        button.disabled = true;
        const setPrintStatus = (message) => {
            if (!status) return;
            status.hidden = false;
            status.textContent = message;
        };
        setPrintStatus(printOrder || printPrescription
            ? 'Preparando la receta médica para imprimir…'
            : 'Preparando las etiquetas…');

        const removeFrame = () => {
            frame.remove();
            button.disabled = false;
        };
        const showError = (error) => {
            console.error(error);
            setPrintStatus(printOrder || printPrescription
                ? 'No se pudo preparar la receta médica. Intenta nuevamente.'
                : 'No se pudo preparar la etiqueta. Intenta nuevamente.');
            removeFrame();
        };

        frame.addEventListener('load', () => {
            if (!frame.contentDocument?.body.classList.contains(expectedBodyClass)) {
                showError(new Error('La respuesta no contiene un documento imprimible.'));
                return;
            }
            if (!printOrder && !printPrescription) {
                const readiness = frame.contentDocument.querySelector('[data-label-print-ready]');
                if (readiness?.dataset.labelPrintReady !== 'true') {
                    setPrintStatus(readiness?.textContent.trim()
                        || 'Configura las dimensiones reales de la etiqueta antes de imprimir.');
                    removeFrame();
                    return;
                }
            }

            const printWindow = frame.contentWindow;
            if (!printWindow) {
                showError(new Error('No se pudo acceder al diálogo de impresión.'));
                return;
            }
            printWindow.addEventListener('afterprint', () => {
                setPrintStatus('Se cerró el diálogo de impresión. Confirma la salida en la impresora.');
                removeFrame();
            }, { once: true });
            setPrintStatus(printPrescription
                ? 'Elige «Guardar como PDF» en el diálogo de impresión.'
                : printOrder
                    ? 'Selecciona la impresora del comprobante en el diálogo de impresión.'
                    : 'Selecciona la impresora de etiquetas y deja una copia por etiqueta.');
            try {
                printWindow.focus();
                printWindow.print();
            } catch (error) {
                showError(error);
            }
        }, { once: true });
        frame.addEventListener('error', () => showError(new Error(
            printOrder || printPrescription
                ? 'No se pudo cargar la receta para imprimir.'
                : 'No se pudo cargar la etiqueta para imprimir.',
        )), { once: true });
        frame.src = printUrl;
        document.body.append(frame);
    });
};
