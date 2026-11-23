export const refreshDistinctProductOptions = (list) => {
    if (!list) return;
    const selects = [...list.querySelectorAll('[data-distinct-products]')];
    const selectedValues = new Set(selects.map((select)  => select.value).filter(Boolean));

    selects.forEach((select)  => {
        select.querySelectorAll('option').forEach((option)  => {
            option.hidden = false;
            option.disabled = option.value !== '' && option.value !== select.value && selectedValues.has(option.value);
        });
    });
    if (list.matches('[data-dispensation-details]')) {
        document.dispatchEvent(new Event('dispensation-products-changed'));
    }
};



export const initializeDynamicForms = () => {
    const treatmentToggle = document.querySelector('[data-prescription-treatment-toggle]');
    const treatmentFields = document.querySelector('[data-prescription-treatment-fields]');
    if (treatmentToggle && treatmentFields) {
        const treatmentInputs = [...treatmentFields.querySelectorAll('input, textarea')];
        const patientRut = document.querySelector('#paciente_rut');
        const patientSearch = document.querySelector('#receta-paciente');
        const patientStatus = treatmentFields.querySelector('[data-prescription-treatment-patient]');

        const updatePatientStatus = () => {
            if (!patientStatus) return;
            const rut = patientRut?.value.trim();
            patientStatus.textContent = rut
                ? `Tratamiento vinculado al paciente ${rut}.`
                : 'Selecciona un paciente en la receta para vincular el tratamiento.';
            patientStatus.classList.toggle('is-linked', Boolean(rut));
        };
        const updateTreatmentState = () => {
            treatmentFields.hidden = !treatmentToggle.checked;
            treatmentInputs.forEach((input) => {
                input.disabled = !treatmentToggle.checked;
                if (input.hasAttribute('data-treatment-required')) {
                    input.required = treatmentToggle.checked;
                }
            });
        };
        treatmentToggle.addEventListener('change', updateTreatmentState);
        patientRut?.addEventListener('change', updatePatientStatus);
        patientSearch?.addEventListener('input', updatePatientStatus);
        updateTreatmentState();
        updatePatientStatus();
    }

    const bindPrescriptionRow = (row) => {
        const product = row.querySelector('[data-prescription-product]');
        const dose = row.querySelector('[data-dose-amount]');
        const duration = row.querySelector('[data-treatment-duration]');
        const frequency = row.querySelector('[data-prescription-frequency]');
        const quantity = row.querySelector('[data-prescribed-quantity]');
        const unit = row.querySelector('[data-dose-unit]');
        const context = row.querySelector('[data-prescription-context]');
        const suggestion = row.querySelector('[data-quantity-suggestion]');
        const applySuggestion = row.querySelector('[data-apply-quantity-suggestion]');
        const quantityLabel = row.querySelector('[data-prescribed-quantity-label]');
        if (!dose || dose.dataset.suggestionBound) return;
        dose.dataset.suggestionBound = 'true';
        const updateProductContext = () => {
            const option = product?.selectedOptions[0];
            const unitCode = option?.dataset.unit || '';
            if (unit) unit.textContent = unitCode ? `Unidad base: ${unitCode}` : '';
            if (context) {
                context.textContent = option?.value
                    ? `Vía de administración: ${option.dataset.route || 'sin especificar'} · Cantidad en ${unitCode || 'unidad base'}`
                    : '';
            }
        };
        const getDosesPerDay = () => {
            const match = frequency?.value.trim().match(/^(?:cada\s*)?(\d+(?:[.,]\d+)?)\s*(?:h|horas?)?$/iu);
            if (!match) return null;

            const hours = Number(match[1].replace(',', '.'));
            return hours > 0 ? 24 / hours : null;
        };
        const updateSuggestion = () => {
            const doseAmount = Number(dose.value);
            const perDay = getDosesPerDay();
            const monthlyLimit = Boolean(treatmentToggle?.checked);
            const days = Number(duration?.value);
            const total = doseAmount * perDay * days;
            const valid = !monthlyLimit && doseAmount > 0 && perDay !== null && days > 0;
            if (quantityLabel) {
                quantityLabel.textContent = monthlyLimit ? 'Cantidad prescrita al mes *' : 'Cantidad prescrita *';
            }
            if (suggestion) {
                suggestion.textContent = monthlyLimit
                    ? 'Ingresa la cantidad máxima autorizada por cada mes calendario según la receta.'
                    : valid
                        ? `Sugerencia: ${total} ${product?.selectedOptions[0]?.dataset.unit || 'unidades'}. Confirma la cantidad prescrita.`
                        : '';
            }
            if (applySuggestion) applySuggestion.hidden = monthlyLimit || !valid;
        };

        product?.addEventListener('change', ()  => {
            updateProductContext();
            updateSuggestion();
        });
        [dose, frequency, duration].forEach((input)  => input?.addEventListener('input', updateSuggestion));
        applySuggestion?.addEventListener('click', ()  => {
            if (treatmentToggle?.checked) return;
            const perDay = getDosesPerDay();
            const total = Number(dose.value) * perDay * Number(duration?.value);
            if (!Number.isFinite(total) || total <= 0 || !quantity) return;
            quantity.value = String(total);
            quantity.dispatchEvent(new Event('input', { bubbles: true }));
        });
        updateProductContext();
        updateSuggestion();
    };

    const bindInventoryAdjustmentRow = (row) => {
        const product = row.querySelector('.adjustment-field-product [data-product-select]');
        const lot = row.querySelector('[data-adjustment-lot]');
        const location = row.querySelector('[data-adjustment-location]');
        const stockLabel = row.querySelector('[data-adjustment-lot-stock]');
        const lotOptionsTemplate = document.getElementById('adjustment-lot-options');
        if (!product || !lot || !lotOptionsTemplate || lot.dataset.adjustmentLotsBound) return;

        lot.dataset.adjustmentLotsBound = 'true';
        const lotOptions = lotOptionsTemplate.content.querySelectorAll('option[data-product]');
        const formatQuantity = (quantity) => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(quantity);
        const updateStockLabel = () => {
            if (!stockLabel) return;
            const option = lot.selectedOptions[0];
            if (!option?.value) {
                stockLabel.textContent = '';
                return;
            }

            const unit = option.dataset.unit || 'unid.';
            if (!location?.value) {
                stockLabel.textContent = `Existencia total del lote: ${formatQuantity(Number(option.dataset.stock || 0))} ${unit}. Selecciona una ubicación para ver su stock.`;
                return;
            }

            const stockByLocation = JSON.parse(option.dataset.stockByLocation || '{}');
            const locationName = location.selectedOptions[0]?.textContent.trim() || 'ubicación seleccionada';
            stockLabel.textContent = `Existencia en ${locationName}: ${formatQuantity(Number(stockByLocation[location.value] || 0))} ${unit}.`;
        };
        const updateLots = () => {
            const selectedLot = lot.value || lot.dataset.selectedLot || '';
            lot.replaceChildren(new Option('Selecciona lote', ''));
            lotOptions.forEach((option) => {
                if (!product.value || option.dataset.product !== product.value) return;
                const matchingOption = option.cloneNode(true);
                if (matchingOption.value === selectedLot) matchingOption.selected = true;
                lot.append(matchingOption);
            });
            lot.dataset.selectedLot = '';
            updateStockLabel();
        };
        product.addEventListener('change', updateLots);
        lot.addEventListener('change', updateStockLabel);
        location?.addEventListener('change', updateStockLabel);
        updateLots();
    };

    const bindDetailRemove = (row) => {
        const button = row.querySelector('.detail-remove');
        if (!button || button.disabled || button.dataset.removeBound) return;

        button.dataset.removeBound = 'true';
        button.addEventListener('click', ()  => {
            const distinctProductsList = row.closest('[data-distinct-products-list]');
            row.remove();
            if (distinctProductsList?.matches('[data-dispensation-details]')) document.dispatchEvent(new Event('dispensation-form:refresh'));
            else refreshDistinctProductOptions(distinctProductsList);
        });
    };

    const bindProgramQuotaRow = (row) => {
        const product = row.querySelector('[data-quota-product], [data-product-select]');
        const fields = [...row.querySelectorAll('[data-quota-field]')];
        if (!product || product.dataset.quotaRequiredBound) return;

        product.dataset.quotaRequiredBound = 'true';
        const updateRequiredFields = (clearEmptyQuota = false) => {
            const hasProduct = Boolean(product.value);
            fields.forEach((field)  => {
                field.required = hasProduct;
                if (!hasProduct && clearEmptyQuota) field.value = '';
            });
        };
        product.addEventListener('change', ()  => updateRequiredFields(true));
        updateRequiredFields();
    };

    document.querySelectorAll('.detail-row').forEach(bindDetailRemove);
    document.querySelectorAll('[data-program-quota-row]').forEach(bindProgramQuotaRow);

    document.querySelectorAll('[data-add-row]').forEach((button)  => {
        button.addEventListener('click', ()  => {
            const target = document.getElementById(button.dataset.addRow);
            const template = document.getElementById(`${button.dataset.addRow.replace(/-details$/, '')}-detail-template`);
            if (!target || !template) return;

            const index = Number(target.dataset.nextIndex ?? target.children.length);
            target.dataset.nextIndex = String(index + 1);
            const row = template.content.firstElementChild.cloneNode(true);
            row.querySelectorAll('[data-name]').forEach((field)  => {
                field.name = `detalles[${index}][${field.dataset.name}]`;
            });
            const productPicker = row.querySelector('[data-product-picker]');
            if (productPicker) {
                const productInput = productPicker.querySelector('[data-product-search-input]');
                const productSelect = productPicker.querySelector('[data-product-select]');
                const results = productPicker.querySelector('[role="listbox"]');
                const help = productPicker.querySelector('[id$="-help"]');
                const stock = productPicker.querySelector('[data-product-availability]');
                const productIdPrefix = target.id === 'prescription-details'
                    ? 'prescription-product'
                    : target.id === 'program-cuota-details'
                        ? 'program-quota-product'
                        : target.id === 'shipment-request-details'
                            ? 'shipment-request-product'
                            : target.id === 'adjustment-details'
                                ? 'adjustment-product'
                            : target.id === 'unidosis-details'
                                ? 'unidosis-product'
                        : 'dispensation-product';
                const productId = `${productIdPrefix}-${index}`;
                productInput.id = productId;
                productInput.dataset.productValueTarget = `${productId}-value`;
                productInput.setAttribute('aria-controls', `${productId}-results`);
                productInput.setAttribute('aria-describedby', [help && `${productId}-help`, stock && `${productId}-stock`].filter(Boolean).join(' '));
                productPicker.querySelector('label').htmlFor = productId;
                productSelect.id = `${productId}-value`;
                results.id = `${productId}-results`;
                if (help) help.id = `${productId}-help`;
                if (stock) stock.id = `${productId}-stock`;
            }
            row.querySelectorAll('[data-adjustment-field]').forEach((field) => {
                const fieldName = field.dataset.adjustmentField;
                field.id = `adjustment-${fieldName}-${index}`;
                row.querySelector(`[data-adjustment-label="${fieldName}"]`)?.setAttribute('for', field.id);
            });
            bindDetailRemove(row);
            target.append(row);
            if (row.matches('[data-adjustment-row]')) bindInventoryAdjustmentRow(row);
            if (row.matches('[data-program-quota-row]')) bindProgramQuotaRow(row);
            if (row.matches('[data-prescription-row]')) bindPrescriptionRow(row);
            if (target.matches('[data-dispensation-details]')) {
                document.dispatchEvent(new Event('dispensation-form:refresh'));
            }
            else refreshDistinctProductOptions(target);
        });
    });

    document.querySelectorAll('[data-prescription-row]').forEach(bindPrescriptionRow);
    document.querySelectorAll('[data-adjustment-row]').forEach(bindInventoryAdjustmentRow);
    treatmentToggle?.addEventListener('change', () => {
        document.querySelectorAll('[data-prescription-row]').forEach((row) => {
            row.querySelector('[data-dose-amount]')?.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    document.querySelectorAll('[data-distinct-products-list]').forEach((list)  => {
        list.addEventListener('change', (event)  => {
            if (event.target.matches('[data-distinct-products]')) refreshDistinctProductOptions(list);
        });
        refreshDistinctProductOptions(list);
    });

    const linkProductToLot = (productSelectId, lotSelectId) => {
        const controlledProduct = document.querySelector(productSelectId);
        const controlledLot = document.querySelector(lotSelectId);
        if (!controlledProduct || !controlledLot) return;
        controlledProduct.addEventListener('change', ()  => {
            controlledLot.value = '';
            controlledLot.querySelectorAll('option[data-product]').forEach((option)  => {
                option.hidden = option.dataset.product !== controlledProduct.value;
                option.disabled = option.hidden;
            });
        });
    };

    linkProductToLot('#control-product', '#control-lot');
    linkProductToLot('#count-control-product', '#count-control-lot');

    const lotSelect = document.querySelector('#lote_id');
    const originSelect = document.querySelector('#origen_id');
    lotSelect?.addEventListener('change', ()  => {
        const locationId = lotSelect.selectedOptions[0]?.dataset.location;
        if (locationId && originSelect) originSelect.value = locationId;
    });
};
