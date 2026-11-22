import { refreshDistinctProductOptions } from './forms';

export const createDispensationPrescriptionRows = ({
    patientSelect,
    recipeSelect,
    dispensationList,
    dispensationEmpty,
    dispensationTemplate,
    dispensationAddButton,
}) => {
    const clearDispensationRows = () => {
        dispensationList?.querySelectorAll('[data-dispensation-row]').forEach((row)  => {
            row.remove();
        });
        if (dispensationEmpty) dispensationEmpty.hidden = false;
        if (dispensationList) dispensationList.dataset.nextIndex = '0';
    };
    const updateDispensationTranscription = (row) => {
        const recipe = recipeSelect?.selectedOptions[0];
        const details = recipe?.value ? JSON.parse(recipe.dataset.details || '[]') : [];
        const productInput = row.querySelector('[data-product-search-input]');
        const productSelect = row.querySelector('[data-product-select]');
        const heading = row.querySelector('[data-dispensation-prescription-heading]');
        const productName = row.querySelector('[data-dispensation-product-name]');
        const deliveryQuantity = row.querySelector('[data-delivery-quantity]');
        const productId = productSelect?.value || '';
        if (heading) heading.hidden = !productId;
        if (productName) productName.textContent = productSelect?.selectedOptions[0]?.textContent.trim() || '';
        const detail = details.find((item)  => String(item.producto_id) === String(productId));
        const isPrescriptionSourceLocked = Boolean(recipe?.value && detail);
        row.classList.toggle('is-prescription-locked', isPrescriptionSourceLocked);
        if (productInput) productInput.readOnly = isPrescriptionSourceLocked;
        [
            '[data-dose-amount]',
            '[data-treatment-duration]',
            '[data-prescription-frequency]',
            '[data-prescribed-quantity]',
            '[name$="[indicaciones]"]',
        ].forEach((selector) => {
            const field = row.querySelector(selector);
            if (field && 'readOnly' in field) field.readOnly = isPrescriptionSourceLocked;
        });
        const sos = row.querySelector('[data-sos-treatment]');
        const prescribedSosValue = row.querySelector('[data-prescribed-sos-value]');
        if (sos) sos.disabled = isPrescriptionSourceLocked;
        if (prescribedSosValue && detail) {
            prescribedSosValue.value = Boolean(Number(detail.es_sos)) ? '1' : '0';
        }
        const clearFieldError = (selector) => row.querySelector(selector)?.setCustomValidity('');
        const clearTranscriptionErrors = () => {
            [
                '[data-dose-amount]',
                '[data-treatment-duration]',
                '[data-prescription-frequency]',
                '[data-prescribed-quantity]',
                '[data-delivery-quantity]',
                '[data-sos-treatment]',
                '[name$="[indicaciones]"]',
            ].forEach(clearFieldError);
        };

        if (!recipe?.value) {
            productInput?.setCustomValidity('');
            if (deliveryQuantity) {
                deliveryQuantity.removeAttribute('max');
                deliveryQuantity.dataset.prescriptionProduct = '';
            }
            clearTranscriptionErrors();

            return false;
        }

        if (!productId || !detail || Number(detail.cantidad_pendiente) <= 0.00001) {
            productInput?.setCustomValidity(productId
                ? 'El medicamento debe pertenecer a la receta vigente seleccionada.'
                : 'Selecciona el medicamento indicado en la receta.');
            if (deliveryQuantity) {
                if (deliveryQuantity.dataset.prescriptionProduct && deliveryQuantity.dataset.prescriptionProduct !== productId) {
                    deliveryQuantity.value = '';
                }
                deliveryQuantity.removeAttribute('max');
                deliveryQuantity.dataset.prescriptionProduct = '';
            }
            clearTranscriptionErrors();

            return false;
        }

        productInput?.setCustomValidity('');
        const pending = Number(detail.cantidad_pendiente);
        const deliveryHelp = row.querySelector('[data-delivery-quantity-help]');
        if (deliveryHelp) {
            deliveryHelp.textContent = detail.es_tratamiento_prolongado
                ? `Límite mensual: ${detail.cantidad_indicada} ${detail.unidad_base_codigo || 'unidades'}. Disponible este mes: ${pending} ${detail.unidad_base_codigo || 'unidades'}.`
                : `Puede ser parcial; saldo pendiente: ${pending} ${detail.unidad_base_codigo || 'unidades'}.`;
        }
        const previousProductId = deliveryQuantity?.dataset.prescriptionProduct || '';
        if (deliveryQuantity) {
            deliveryQuantity.max = String(pending);
            if (previousProductId !== String(productId)) {
                if (previousProductId || !deliveryQuantity.value) deliveryQuantity.value = String(pending);
                deliveryQuantity.dataset.prescriptionProduct = String(productId);
            }
        }

        let hasMismatch = false;
        let isIncomplete = false;
        const compareNumber = (selector, expected, label, alwaysCompare = false) => {
            const field = row.querySelector(selector);
            if (!field) return;
            if (expected === null || expected === undefined || expected === '') {
                if (!alwaysCompare) {
                    field.setCustomValidity('');

                    return;
                }
            }
            if (field.value === '') {
                field.setCustomValidity(`${label} debe coincidir con la receta vigente.`);
                isIncomplete = true;

                return;
            }
            const actual = Number(field.value);
            const isMatch = Number.isFinite(actual)
                && Math.abs(actual - Number(expected)) <= 0.00001;
            field.setCustomValidity(isMatch ? '' : `${label} debe coincidir con la receta vigente.`);
            hasMismatch = hasMismatch || ! isMatch;
        };
        const compareText = (selector, expected, label) => {
            const field = row.querySelector(selector);
            if (!field || expected === null || expected === undefined) return;
            const actual = selector === '[data-prescription-frequency]' && row.querySelector('[data-sos-treatment]')?.checked
                ? 'SOS'
                : field.value.trim();
            if (actual === '') {
                field.setCustomValidity(`${label} debe coincidir con la receta vigente.`);
                isIncomplete = true;

                return;
            }
            const isMatch = actual === String(expected).trim();
            field.setCustomValidity(isMatch ? '' : `${label} debe coincidir con la receta vigente.`);
            hasMismatch = hasMismatch || ! isMatch;
        };
        const sosMatches = Boolean(sos?.checked) === Boolean(Number(detail.es_sos));
        sos?.setCustomValidity(sosMatches ? '' : 'La indicación SOS debe coincidir con la receta vigente.');
        hasMismatch = hasMismatch || ! sosMatches;
        compareNumber('[data-dose-amount]', detail.unidades_por_toma, 'La dosis por toma');
        compareNumber('[data-treatment-duration]', detail.duracion_dias, 'La duración');
        compareNumber('[data-prescribed-quantity]', detail.cantidad_indicada, 'La cantidad prescrita', true);
        compareText('[data-prescription-frequency]', detail.frecuencia, 'La frecuencia');
        compareText('[name$="[indicaciones]"]', detail.indicaciones, 'Las indicaciones');
        isIncomplete = isIncomplete || [...row.querySelectorAll('[required]')]
            .some((field) /* Comprueba si algún elemento cumple el criterio. */ => !field.disabled && field.value.trim() === '');

        return !hasMismatch && !isIncomplete;
    };
    const fillDispensationRowFromPrescription = (row, detail, preserveEnteredValues = false) => {
        const productSelect = row.querySelector('[data-product-select]');
        const productInput = row.querySelector('[data-product-search-input]');
        if (!productSelect || !productInput) return;

        const productId = String(detail.producto_id);
        let option = [...productSelect.options].find((item) => item.value === productId);
        if (!option) {
            option = new Option(detail.producto, productId);
            productSelect.add(option);
        }
        option.textContent = detail.producto;
        option.dataset.controlled = String(Number(detail.es_controlado));
        option.dataset.stock = detail.stock_disponible === null || detail.stock_disponible === undefined
            ? ''
            : String(detail.stock_disponible);
        if (detail.unidad_base_codigo) option.dataset.unit = detail.unidad_base_codigo;
        if (detail.via_administracion) option.dataset.route = detail.via_administracion;

        if (!preserveEnteredValues || !productSelect.value) {
            productSelect.value = productId;
        }
        if (productSelect.value === productId) {
            productInput.value = detail.producto;
            productInput.setCustomValidity('');
            row.querySelector('[data-dose-amount]').value = detail.unidades_por_toma ?? '';
            row.querySelector('[data-treatment-duration]').value = detail.duracion_dias ?? '';
            row.querySelector('[data-prescription-frequency]').value = detail.frecuencia ?? '';
            row.querySelector('[data-prescribed-quantity]').value = detail.cantidad_indicada ?? '';
            row.querySelector('[data-prescribed-sos-value]').value = Boolean(Number(detail.es_sos)) ? '1' : '0';
            row.querySelector('[data-sos-treatment]').checked = Boolean(Number(detail.es_sos));
            row.querySelector('[name$="[indicaciones]"]').value = detail.indicaciones ?? '';
            if (!preserveEnteredValues) row.querySelector('[data-delivery-quantity]').value = '';
        }
        productSelect.dispatchEvent(new Event('change', { bubbles: true }));
        row.querySelector('[data-sos-treatment]').dispatchEvent(new Event('change', { bubbles: true }));
    };
    const renderPrescriptionDetails = () => {
        const recipe = recipeSelect?.selectedOptions[0];
        if (!dispensationList || !dispensationTemplate || !dispensationAddButton) return;

        if (!recipe?.value) {
            dispensationAddButton.hidden = true;
            dispensationList.dataset.renderedRecipeId = '';
            const rows = [...dispensationList.querySelectorAll('[data-dispensation-row]')];
            rows.forEach(updateDispensationTranscription);
            if (dispensationEmpty) {
                dispensationEmpty.textContent = patientSelect?.value
                    ? 'Selecciona una receta vigente para cargar los medicamentos prescritos.'
                    : 'Selecciona un paciente para cargar sus recetas vigentes.';
                dispensationEmpty.hidden = rows.length > 0;
            }
            refreshDistinctProductOptions(dispensationList);

            return;
        }

        const recipeDetails = JSON.parse(recipe.dataset.details || '[]');
        const pendingDetails = recipeDetails.filter((detail) => Number(detail.cantidad_pendiente) > 0.00001);
        const recipeId = String(recipe.value);
        if (dispensationList.dataset.renderedRecipeId !== recipeId) {
            const previousRecipeId = dispensationList.dataset.renderedRecipeId;
            if (previousRecipeId || pendingDetails.length === 0) clearDispensationRows();
            const initialRows = [...dispensationList.querySelectorAll('[data-dispensation-row]')];
            const usedRows = new Set();
            pendingDetails.forEach((detail) => {
                const productId = String(detail.producto_id);
                let row = initialRows.find((candidate) => (
                    !usedRows.has(candidate)
                    && candidate.querySelector('[data-product-select]')?.value === productId
                ));
                let preserveEnteredValues = Boolean(row);
                if (!row) {
                    row = initialRows.find((candidate) => (
                        !usedRows.has(candidate)
                        && !candidate.querySelector('[data-product-select]')?.value
                    ));
                    preserveEnteredValues = false;
                }
                if (!row) {
                    dispensationAddButton.click();
                    row = dispensationList.querySelector('[data-dispensation-row]:last-child');
                }
                if (!row) return;
                usedRows.add(row);
                fillDispensationRowFromPrescription(row, detail, preserveEnteredValues);
            });
            dispensationList.dataset.renderedRecipeId = recipeId;
        }

        if (pendingDetails.length === 0) {
            dispensationAddButton.hidden = true;
            clearDispensationRows();
            if (dispensationEmpty) {
                dispensationEmpty.textContent = 'La receta seleccionada no tiene medicamentos pendientes.';
                dispensationEmpty.hidden = false;
            }
            dispensationList.querySelectorAll('[data-dispensation-row]').forEach(updateDispensationTranscription);

            return;
        }

        dispensationAddButton.hidden = true;
        dispensationList.querySelectorAll('[data-dispensation-row]').forEach((row)  => {
            updateDispensationTranscription(row);
        });
        if (dispensationEmpty) dispensationEmpty.hidden = true;
        refreshDistinctProductOptions(dispensationList);
    };
    const refresh = () => {
        refreshDistinctProductOptions(dispensationList);
        const rows = [...dispensationList?.querySelectorAll('[data-dispensation-row]') || []];
        rows.forEach(updateDispensationTranscription);

        return rows;
    };

    return {
        clear: clearDispensationRows,
        render: renderPrescriptionDetails,
        update: updateDispensationTranscription,
        refresh,
    };
};
