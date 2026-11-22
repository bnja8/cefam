const formUnitCodes = {
    'comprimido': ['comprimido'],
    'cápsula': ['cápsula'],
    'solución oral': ['mL', 'L'],
    'suspensión oral': ['mL', 'L'],
    'polvo': ['sobre'],
    'polvo para suspensión': ['mL', 'L', 'sobre'],
    'crema': ['g'],
    'pomada': ['g'],
    'gel': ['g'],
    'jarabe': ['mL', 'L'],
    'inyectable': ['mL'],
    'gotas': ['mL'],
    'inhalador': ['dosis'],
    'supositorio': ['unidad'],
    'parche transdérmico': ['unidad'],
};
export const initializeProductCatalogForms = () => {
    document.querySelectorAll('[data-product-catalog-form]').forEach((form)  => {
        const productKind = form.querySelector('[data-product-kind]');
        const medicationFields = form.querySelector('[data-medication-fields]');
        const pharmaceuticalForm = form.querySelector('[data-pharmaceutical-form]');
        const controlledCheckbox = form.querySelector('[data-controlled-product]');
        const controlledFields = form.querySelector('[data-controlled-fields]');
        const ingredientList = form.querySelector('[data-ingredient-list]');
        const ingredientTemplate = form.querySelector('[data-ingredient-template]');
        const presentationList = form.querySelector('[data-presentation-list]');
        const presentationTemplate = form.querySelector('[data-presentation-template]');
        const commercialPresentationList = form.querySelector('[data-commercial-presentation-list]');
        const commercialPresentationTemplate = form.querySelector('[data-commercial-presentation-template]');
        const packageContentUnit = form.querySelector('[data-unit-role="package-content"]');
        const baseInventoryUnit = form.querySelector('[data-unit-role="base-inventory"]');
        const updateFormDependentUnits = () => {
            const formName = productKind?.value === 'MEDICAMENTO'
                ? pharmaceuticalForm?.selectedOptions[0]?.dataset.formName?.toLocaleLowerCase()
                : null;
            const allowedCodes = formUnitCodes[formName] ?? null;
            form.querySelectorAll('[data-form-dependent-unit]').forEach((select)  => {
                const currentValue = select.value;
                select.querySelectorAll('option[data-unit-code]').forEach((option)  => {
                    const allowed = select.dataset.unitRole === 'base-inventory'
                        || !allowedCodes
                        || allowedCodes.includes(option.dataset.unitCode);
                    option.hidden = !allowed;
                    option.disabled = !allowed;
                });
                if (currentValue && select.selectedOptions[0]?.disabled) {
                    select.value = '';
                    if (select.dataset.unitRole === 'base-inventory') return;
                    const amountSelector = select.dataset.unitRole === 'concentration-base'
                        ? '[data-concentration-base-amount]'
                        : '[data-package-content-amount]';
                    const amount = select.closest('.form-grid')?.querySelector(amountSelector);
                    if (amount) amount.value = '';
                }
            });
        };
        const updateConcentrationBaseRequirement = (row) => {
            const active = productKind?.value === 'MEDICAMENTO';
            const unit = row.querySelector('[data-concentration-unit]');
            const percentage = unit?.selectedOptions[0]?.dataset.unitCode === '%';
            row.querySelectorAll('[data-concentration-base-amount], [data-unit-role="concentration-base"]').forEach((field)  => {
                field.required = Boolean(active && !percentage);
            });
        };
        const setMedicineFields = () => {
            const enabled = productKind?.value === 'MEDICAMENTO';
            if (medicationFields) {
                medicationFields.hidden = !enabled;
                medicationFields.disabled = !enabled;
                medicationFields.querySelectorAll('[data-required-when-medicine]').forEach((field)  => {
                    field.required = enabled;
                });
            }
            updateFormDependentUnits();
            ingredientList?.querySelectorAll('[data-ingredient-row]').forEach(updateConcentrationBaseRequirement);
            updateControlledFields();
        };
        const updateControlledFields = () => {
            if (!controlledFields) return;
            const enabled = productKind?.value === 'MEDICAMENTO' && controlledCheckbox?.checked;
            controlledFields.hidden = !enabled;
            controlledFields.querySelectorAll('[data-required-when-controlled]').forEach((field)  => {
                field.required = Boolean(enabled);
                field.disabled = !enabled;
            });
        };

        const bindIngredientRow = (row) => {
            const concentrationUnit = row.querySelector('[data-concentration-unit]');
            concentrationUnit?.addEventListener('change', ()  => updateConcentrationBaseRequirement(row));
            row.querySelector('[data-remove-ingredient]')?.addEventListener('click', ()  => {
                if (ingredientList.children.length > 1) {
                    row.remove();
                    return;
                }
                row.querySelectorAll('input').forEach((input)  => { input.value = ''; });
                row.querySelectorAll('select').forEach((select)  => { select.value = ''; });
                updateConcentrationBaseRequirement(row);
            });
            updateConcentrationBaseRequirement(row);
        };

        form.querySelectorAll('[data-ingredient-row]').forEach(bindIngredientRow);
        const bindPresentationRow = (row) => {
            row.querySelector('[data-remove-presentation]')?.addEventListener('click', ()  => row.remove());
        };
        presentationList?.querySelectorAll('.detail-row').forEach(bindPresentationRow);
        const bindCommercialPresentationRow = (row) => {
            row.querySelector('[data-remove-commercial-presentation]')?.addEventListener('click', () => row.remove());
        };
        commercialPresentationList?.querySelectorAll('.detail-row').forEach(bindCommercialPresentationRow);
        form.querySelector('[data-add-ingredient]')?.addEventListener('click', ()  => {
            if (!ingredientList || !ingredientTemplate) return;
            const index = Number(ingredientList.dataset.nextIndex || ingredientList.children.length);
            ingredientList.dataset.nextIndex = String(index + 1);
            const row = ingredientTemplate.content.firstElementChild.cloneNode(true);
            row.querySelectorAll('[name]').forEach((field)  => {
                field.name = field.name.replace(/__INDEX__/g, String(index));
            });
            row.querySelectorAll('[data-required-when-medicine]').forEach((field)  => {
                field.required = productKind?.value === 'MEDICAMENTO';
            });
            ingredientList.append(row);
            bindIngredientRow(row);
            updateFormDependentUnits();
        });
        form.querySelector('[data-add-presentation]')?.addEventListener('click', ()  => {
            if (!presentationList || !presentationTemplate) return;
            const index = Number(presentationList.dataset.nextIndex || presentationList.children.length);
            presentationList.dataset.nextIndex = String(index + 1);
            const row = presentationTemplate.content.firstElementChild.cloneNode(true);
            row.querySelectorAll('[data-name]').forEach((field)  => {
                field.name = `presentaciones[${index}][${field.dataset.name}]`;
            });
            presentationList.append(row);
            bindPresentationRow(row);
        });
        form.querySelector('[data-add-commercial-presentation]')?.addEventListener('click', () => {
            if (!commercialPresentationList || !commercialPresentationTemplate) return;
            const index = Number(commercialPresentationList.dataset.nextIndex || commercialPresentationList.children.length);
            commercialPresentationList.dataset.nextIndex = String(index + 1);
            const row = commercialPresentationTemplate.content.firstElementChild.cloneNode(true);
            row.querySelector('[data-commercial-presentation-name]').name = `presentaciones_comerciales[${index}]`;
            commercialPresentationList.append(row);
            bindCommercialPresentationRow(row);
        });
        productKind?.addEventListener('change', setMedicineFields);
        pharmaceuticalForm?.addEventListener('change', ()  => {
            updateFormDependentUnits();
            ingredientList?.querySelectorAll('[data-ingredient-row]').forEach(updateConcentrationBaseRequirement);
        });
        packageContentUnit?.addEventListener('change', updateFormDependentUnits);
        baseInventoryUnit?.addEventListener('change', updateFormDependentUnits);
        controlledCheckbox?.addEventListener('change', updateControlledFields);
        setMedicineFields();
    });
};
