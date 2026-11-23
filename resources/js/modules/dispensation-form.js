import { createDispensationPrescriptionRows } from './dispensation-prescription-rows';
import { formatQuantity } from './format';

export const initializeDispensationForm = () => {
    const patientSelect = document.querySelector('#paciente_rut');
    const recipeSelect = document.querySelector('#receta_id');
    const recipeSelectLabel = document.querySelector('[data-recipe-select-label]');
    const recipeSelectHelp = document.querySelector('[data-recipe-select-help]');
    const authorizedPersonSelect = document.querySelector('#persona_autorizada_id');
    const dispensationList = document.querySelector('[data-dispensation-details]');
    const dispensationForm = dispensationList?.closest('[data-dispensation-form]');
    const dispensationEmpty = document.querySelector('[data-dispensation-empty]');
    const dispensationTemplate = document.querySelector('#dispensation-detail-template');
    const dispensationAddButton = document.querySelector('[data-add-row="dispensation-details"]');
    const sosModeInput = document.querySelector('[data-treatment-sos-mode]');
    const sosModeToggle = document.querySelector('[data-toggle-sos-treatment]');
    const modeTitle = document.querySelector('[data-dispensation-mode-title]');
    const patientSearchInput = document.querySelector('#patient-search');
    const prescriptionVerification = document.querySelector('[data-prescription-verification]');
    const prescriptionConfirmation = document.querySelector('[data-prescription-confirmation]');
    const prescriptionMedicineRows = prescriptionVerification?.querySelector('[data-recipe-medicine-rows]');
    const recipeSummary = document.querySelector('[data-dispensation-recipe-summary]');
    const authorizedPersonPanel = document.querySelector('[data-new-authorized-person]');
    const authorizedPersonToggle = document.querySelector('[data-toggle-authorized-person]');
    const authorizedPersonButtonLabel = document.querySelector('[data-authorized-person-button-label]');
    const authorizedPersonPatient = document.querySelector('[data-authorized-person-patient]');
    const authorizedPersonFields = [...document.querySelectorAll('[data-new-authorized-person-field]')];
    const authorizationDialog = document.querySelector('[data-controlled-auth]');
    const authorizerRut = authorizationDialog?.querySelector('[data-controlled-authorizer-rut]');
    const authorizerPassword = authorizationDialog?.querySelector('[data-controlled-authorizer-password]');
    const authorizationConfirm = authorizationDialog?.querySelector('[data-controlled-auth-confirm]');
    const authorizationCancel = authorizationDialog?.querySelector('[data-controlled-auth-cancel]');
    const dispensationSubmit = dispensationForm?.querySelector('button[type="submit"]');
    let activePatientRut = patientSelect?.value || '';
    let activeProgramName = '';
    let authorizedPersonFormOpen = authorizedPersonPanel?.dataset.openOnLoad === 'true';
    let verifiedRecipeId = '';
    let authorizationConfirmed = false;
    let isSosMode = sosModeInput?.value === '1';
    const dispensationRows = createDispensationPrescriptionRows({
        patientSelect,
        recipeSelect,
        dispensationList,
        dispensationEmpty,
        dispensationTemplate,
        dispensationAddButton,
    });
    const formatPrescriptionDate = (date) => {
        if (!date) return 'Sin fecha';
        const [year, month, day] = String(date).slice(0, 10).split('-');
        return year && month && day ? `${day}/${month}/${year}` : String(date);
    };
    const formatPrescriptionFrequency = (frequency) => {
        const value = String(frequency || '').trim();
        if (!value) return '—';
        const interval = value.match(/^(?:cada\s*)?(\d+(?:[.,]\d+)?)\s*(?:h|horas?)?$/iu);
        if (interval) return `Cada ${interval[1].replace(',', '.')} horas`;
        return /^cada\b/iu.test(value) ? value : `Cada ${value}`;
    };
    const renderPrescriptionMedicines = (recipe) => {
        if (!prescriptionMedicineRows) return;
        const details = Array.isArray(recipe.detalles) ? recipe.detalles : [];
        const rows = details.map((detail) => {
            const dose = detail.dosis?.trim()
                || (detail.unidades_por_toma
                    ? `${formatQuantity(detail.unidades_por_toma)} ${detail.unidad_base_codigo || ''}`.trim()
                    : '—');
            const duration = Number(detail.es_sos)
                ? 'SOS · máximo prescrito'
                : (detail.duracion_dias ? `${detail.duracion_dias} días` : '—');
            const quantity = detail.cantidad_indicada
                ? `${formatQuantity(detail.cantidad_indicada)} ${detail.unidad_base_codigo || 'unid.'}`
                : '—';
            const values = [
                detail.producto || 'Sin nombre',
                dose,
                formatPrescriptionFrequency(detail.frecuencia),
                duration,
                quantity,
                detail.indicaciones?.trim() || '—',
            ];
            const row = document.createElement('tr');
            values.forEach((value, index) => {
                const cell = document.createElement('td');
                cell.dataset.label = [
                    'Fármaco prescrito',
                    'Dosis / toma',
                    'Frecuencia',
                    'Duración',
                    'Cant. prescrita',
                    'Indicaciones médicas',
                ][index];
                cell.textContent = value;
                row.append(cell);
            });
            return row;
        });
        if (!rows.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.textContent = 'La receta no tiene fármacos prescritos.';
            row.append(cell);
            rows.push(row);
        }
        prescriptionMedicineRows.replaceChildren(...rows);
    };
    const updateAuthorizedPersonPatient = () => {
        if (!authorizedPersonPatient) return;
        authorizedPersonPatient.textContent = patientSelect?.value
            ? `Paciente vinculado: ${patientSearchInput?.value.trim() || patientSelect.value}`
            : 'Selecciona un paciente para asociar la autorización.';
    };
    const updateAuthorizedPersonPlaceholder = () => {
        const placeholder = authorizedPersonSelect?.options[0];
        if (placeholder) placeholder.textContent = isSosMode ? 'SOS' : 'No, retira el paciente';
    };
    const syncAuthorizedPersonForm = () => {
        if (!authorizedPersonPanel) return;
        authorizedPersonPanel.hidden = !authorizedPersonFormOpen;
        authorizedPersonFields.forEach((field) => {
            field.disabled = !authorizedPersonFormOpen;
            field.required = authorizedPersonFormOpen;
        });
        if (authorizedPersonToggle) {
            authorizedPersonToggle.disabled = isSosMode || !patientSelect?.value;
            authorizedPersonToggle.setAttribute('aria-expanded', String(authorizedPersonFormOpen));
        }
        if (authorizedPersonButtonLabel) {
            authorizedPersonButtonLabel.textContent = authorizedPersonFormOpen
                ? 'Cerrar formulario'
                : 'Registrar persona autorizada';
        }
        updateAuthorizedPersonPatient();
    };
    const setAuthorizedPersonFormOpen = (isOpen, { keepAuthorizedPersonSelection = false, clearFields = false } = {}) => {
        if (isOpen && (!patientSelect?.value || isSosMode)) return;
        authorizedPersonFormOpen = isOpen;

        if (authorizedPersonSelect) {
            let newAuthorizationOption = authorizedPersonSelect.querySelector('[data-new-authorized-person-option]');
            if (isOpen) {
                if (!newAuthorizationOption) {
                    newAuthorizationOption = new Option('Nueva autorización · Se guardará al confirmar la dispensación', '__nueva_autorizacion__');
                    newAuthorizationOption.dataset.newAuthorizedPersonOption = 'true';
                    authorizedPersonSelect.add(newAuthorizationOption, 1);
                }
                newAuthorizationOption.selected = true;
                authorizedPersonSelect.removeAttribute('name');
            } else {
                newAuthorizationOption?.remove();
                authorizedPersonSelect.name = 'persona_autorizada_id';
                if (!keepAuthorizedPersonSelection) authorizedPersonSelect.value = '';
            }
        }
        if (clearFields) authorizedPersonFields.forEach((field) => {
            field.value = '';
        });
        syncAuthorizedPersonForm();
    };
    const updateDispensationMode = () => {
        const hasPatient = Boolean(patientSelect?.value);
        const hasRecipe = Boolean(recipeSelect?.value) && !isSosMode;
        if (dispensationList) dispensationList.dataset.treatmentSos = String(isSosMode);
        if (recipeSelect) {
            recipeSelect.disabled = isSosMode || !hasPatient;
            recipeSelect.required = !isSosMode;
        }
        if (recipeSelectLabel) recipeSelectLabel.textContent = isSosMode ? 'Receta vigente' : 'Receta vigente *';
        if (recipeSelectHelp) {
            recipeSelectHelp.textContent = isSosMode
                ? 'No se requiere una receta vigente para el tratamiento SOS.'
                : 'Al seleccionar una receta, se cargan sus medicamentos y lotes FEFO.';
        }
        if (authorizedPersonSelect) {
            authorizedPersonSelect.disabled = isSosMode || !hasPatient;
            updateAuthorizedPersonPlaceholder();
        }
        if (authorizedPersonToggle) authorizedPersonToggle.disabled = isSosMode || !hasPatient;
        if (sosModeInput) sosModeInput.value = isSosMode ? '1' : '0';
        if (sosModeToggle) {
            sosModeToggle.textContent = isSosMode ? 'Volver a dispensación con receta' : 'Tratamiento SOS';
            sosModeToggle.setAttribute('aria-pressed', String(isSosMode));
        }
        if (modeTitle) modeTitle.textContent = isSosMode ? 'Medicamentos SOS' : 'Medicamentos prescritos';

        const rows = [...dispensationList?.querySelectorAll('[data-dispensation-row]') || []];
        const canEditRows = hasPatient && (isSosMode || hasRecipe);
        rows.forEach((row) => {
            row.hidden = !isSosMode && !canEditRows;
            row.classList.toggle('is-sos-mode', isSosMode);
            row.querySelectorAll('[data-prescription-only]').forEach((group) => {
                group.hidden = false;
                group.querySelectorAll('input, select, textarea').forEach((field) => {
                    field.disabled = isSosMode || !canEditRows;
                    if (field.matches('[data-dose-amount], [data-treatment-duration], [data-prescription-frequency], [data-prescribed-quantity]')) {
                        field.required = !isSosMode;
                    }
                });
            });
            const productHelp = row.querySelector('.product-search-help');
            if (productHelp) {
                productHelp.textContent = isSosMode
                    ? 'Busca el medicamento que se entregará.'
                    : 'Busca el medicamento indicado en la receta y selecciona un resultado.';
            }
            row.querySelectorAll('input, select, textarea').forEach((field) => {
                if (!field.closest('[data-prescription-only]')) {
                    field.disabled = !canEditRows;
                }
            });
        });
        if (dispensationAddButton) {
            if (isSosMode) {
                dispensationAddButton.hidden = false;
                dispensationAddButton.disabled = false;
            } else {
                dispensationAddButton.disabled = false;
                if (!hasRecipe) dispensationAddButton.hidden = true;
            }
        }
        if (isSosMode && rows.length === 0) dispensationAddButton?.click();
    };
    const updatePrescriptionVerification = () => {
        const recipeOption = recipeSelect?.selectedOptions[0];
        if (!prescriptionVerification || !prescriptionConfirmation) return;
        if (isSosMode || !recipeOption?.value) {
            prescriptionVerification.hidden = true;
            prescriptionConfirmation.disabled = true;
            prescriptionConfirmation.required = false;
            prescriptionConfirmation.checked = false;
            prescriptionMedicineRows?.replaceChildren();
            if (recipeSummary) recipeSummary.textContent = '';
            return;
        }

        const recipe = JSON.parse(recipeOption.dataset.recipe || '{}');
        prescriptionVerification.hidden = false;
        prescriptionConfirmation.disabled = false;
        prescriptionConfirmation.required = true;
        prescriptionVerification.querySelector('[data-recipe-folio]').textContent = `Folio ${recipe.numero_receta || ''}`;
        prescriptionVerification.querySelector('[data-recipe-prescriber]').textContent = recipe.profesional || 'Sin información';
        prescriptionVerification.querySelector('[data-recipe-prescriber-rut]').textContent = `RUT ${recipe.profesional_rut || 'Sin información'}`;
        prescriptionVerification.querySelector('[data-recipe-emission]').textContent = `Emisión: ${formatPrescriptionDate(recipe.fecha_emision)}`;
        prescriptionVerification.querySelector('[data-recipe-expiration]').textContent = `Válida hasta: ${formatPrescriptionDate(recipe.fecha_vencimiento)}`;
        prescriptionVerification.querySelector('[data-recipe-program]').textContent = recipe.programa_activo || 'Sin programa activo asociado';
        prescriptionVerification.querySelector('[data-recipe-observation]').textContent = recipe.observacion?.trim() || 'Sin observación registrada';
        renderPrescriptionMedicines(recipe);
        if (recipeSummary) {
            const count = recipe.detalles?.length || 0;
            recipeSummary.textContent = `${count} medicamento${count === 1 ? '' : 's'} cargado${count === 1 ? '' : 's'} desde ${recipe.numero_receta}`;
        }
    };
    const hasControlledProduct = () => [...dispensationList?.querySelectorAll('[data-product-select]') || []]
        .some((select) /* Comprueba si algún elemento cumple el criterio. */ => select.selectedOptions[0]?.dataset.controlled === '1');
    const updateControlledAuthorization = () => {
        if (hasControlledProduct()) return;

        authorizationConfirmed = false;
        if (authorizerRut) {
            authorizerRut.required = false;
            authorizerRut.value = '';
        }
        if (authorizerPassword) {
            authorizerPassword.required = false;
            authorizerPassword.value = '';
        }
        if (authorizationDialog?.open) authorizationDialog.close();
    };
    const openAuthorizationDialog = () => {
        if (!authorizationDialog || !authorizerRut || !authorizerPassword) return;

        authorizerRut.required = true;
        authorizerPassword.required = true;
        if (!authorizationDialog.open) authorizationDialog.showModal();
        authorizerRut.focus();
    };
    const closeAuthorizationDialog = () => {
        if (authorizationDialog?.open) authorizationDialog.close();
        authorizationConfirmed = false;
        if (authorizerRut) {
            authorizerRut.required = false;
            authorizerRut.value = '';
        }
        if (authorizerPassword) {
            authorizerPassword.required = false;
            authorizerPassword.value = '';
        }
        dispensationSubmit?.focus();
    };
    const loadPatientOptions = async () => {
        if (!patientSelect || !recipeSelect || !authorizedPersonSelect) return;
        const nextPatientRut = patientSelect.value;
        if (nextPatientRut && activePatientRut && activePatientRut !== nextPatientRut) {
            dispensationRows.clear();
            setAuthorizedPersonFormOpen(false, { clearFields: true });
            if (prescriptionConfirmation) prescriptionConfirmation.checked = false;
            if (dispensationAddButton) {
                dispensationAddButton.hidden = false;
                if (isSosMode || recipeSelect.value) dispensationAddButton.click();
            }
        }
        if (nextPatientRut) activePatientRut = nextPatientRut;
        if (!nextPatientRut) {
            activeProgramName = '';
            setAuthorizedPersonFormOpen(false, { clearFields: true });
        }
        const placeholderOptions = [
            [recipeSelect, 'Seleccione la receta vigente'],
            [authorizedPersonSelect, isSosMode ? 'SOS' : 'No, retira el paciente'],
        ];
        const requestId = String(Number(patientSelect.dataset.optionsRequest || 0) + 1);
        patientSelect.dataset.optionsRequest = requestId;
        placeholderOptions.forEach(([select, label])  => {
            select.replaceChildren(new Option(label, ''));
            select.value = '';
            select.disabled = isSosMode || !patientSelect.value;
        });
        recipeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        updateDispensationMode();
        if (!patientSelect.value) return;
        if (isSosMode) return;

        try {
            const url = patientSelect.dataset.dispensationOptionsUrl.replace('__PATIENT__', encodeURIComponent(patientSelect.value));
            const response = await fetch(url, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('No se pudieron cargar las opciones del paciente.');
            const options = await response.json();
            if (patientSelect.dataset.optionsRequest !== requestId) return;
            activeProgramName = options.programa_activo || '';

            options.recetas.forEach((recipe)  => {
                const option = new Option(`${recipe.numero_receta} · Emisión ${formatPrescriptionDate(recipe.fecha_emision)}`, recipe.receta_id);
                option.dataset.details = JSON.stringify(recipe.detalles);
                option.dataset.recipe = JSON.stringify({ ...recipe, programa_activo: activeProgramName });
                recipeSelect.add(option);
            });
            options.personas_autorizadas.forEach((authorizedPerson)  => {
                const option = new Option(`${authorizedPerson.nombre_completo} · ${authorizedPerson.rut}`, authorizedPerson.persona_autorizada_id);
                authorizedPersonSelect.add(option);
            });
            recipeSelect.disabled = false;
            authorizedPersonSelect.disabled = false;
            updateDispensationMode();
            syncAuthorizedPersonForm();
            if (authorizedPersonFormOpen) setAuthorizedPersonFormOpen(true);
            const selectedRecipeId = recipeSelect.dataset.selectedRecipe;
            if (selectedRecipeId && options.recetas.some((recipe) /* Comprueba si algún elemento cumple el criterio. */ => String(recipe.receta_id) === selectedRecipeId)) {
                recipeSelect.value = selectedRecipeId;
                recipeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                delete recipeSelect.dataset.selectedRecipe;
            }
        } catch (error) {
            if (patientSelect.dataset.optionsRequest !== requestId) return;
            console.error(error);
        }
    };
    const setSosMode = (enabled) => {
        if (enabled === isSosMode) return;
        const hasSelectedData = Boolean(recipeSelect?.value)
            || [...dispensationList?.querySelectorAll('[data-dispensation-row]') || []]
                .some((row) => row.querySelector('[data-product-select]')?.value || row.querySelector('[data-delivery-quantity]')?.value);
        if (hasSelectedData && !window.confirm('Al cambiar el modo se descartarán los medicamentos cargados. ¿Deseas continuar?')) return;

        isSosMode = enabled;
        if (recipeSelect) {
            recipeSelect.value = '';
            delete recipeSelect.dataset.selectedRecipe;
        }
        verifiedRecipeId = '';
        if (prescriptionConfirmation) prescriptionConfirmation.checked = false;
        setAuthorizedPersonFormOpen(false, { clearFields: true });
        dispensationRows.clear();
        if (authorizedPersonSelect) {
            authorizedPersonSelect.value = '';
            updateAuthorizedPersonPlaceholder();
        }
        updatePrescriptionVerification();
        dispensationRows.render();
        updateDispensationMode();
        if (!isSosMode && patientSelect?.value) loadPatientOptions();
        applyPrescription();
    };
    sosModeToggle?.addEventListener('click', () => setSosMode(!isSosMode));
    authorizedPersonToggle?.addEventListener('click', () => {
        setAuthorizedPersonFormOpen(!authorizedPersonFormOpen);
    });
    authorizedPersonSelect?.addEventListener('change', () => {
        if (authorizedPersonFormOpen && authorizedPersonSelect.value !== '__nueva_autorizacion__') {
            setAuthorizedPersonFormOpen(false, { keepAuthorizedPersonSelection: true });
        }
    });
    patientSearchInput?.addEventListener('input', updateAuthorizedPersonPatient);
    if (authorizedPersonFormOpen) setAuthorizedPersonFormOpen(true);
    else syncAuthorizedPersonForm();
    if (isSosMode && recipeSelect) recipeSelect.value = '';
    updateDispensationMode();
    patientSelect?.addEventListener('change', loadPatientOptions);
    if (patientSelect?.value && !isSosMode) loadPatientOptions();
    const applyPrescription = () => {
        const rows = dispensationRows.refresh();
        if (dispensationEmpty) dispensationEmpty.hidden = rows.length > 0;
        updateControlledAuthorization();
        updateDispensationMode();
    };
    recipeSelect?.addEventListener('change', ()  => {
        const recipeId = recipeSelect.value;
        if (recipeId !== verifiedRecipeId) {
            if (prescriptionConfirmation) prescriptionConfirmation.checked = false;
            verifiedRecipeId = recipeId;
        }
        updatePrescriptionVerification();
        dispensationRows.render();
        applyPrescription();
    });
    dispensationList?.addEventListener('change', (event)  => {
        const row = event.target.closest('[data-dispensation-row]');
        if (row) dispensationRows.update(row);
        if (event.target.matches('[data-product-select]')) updateControlledAuthorization();
    });
    dispensationList?.addEventListener('input', (event)  => {
        const row = event.target.closest('[data-dispensation-row]');
        if (row) dispensationRows.update(row);
    });
    dispensationForm?.addEventListener('submit', (event)  => {
        if (!hasControlledProduct() || authorizationConfirmed) {
            authorizationConfirmed = false;
            return;
        }

        event.preventDefault();
        openAuthorizationDialog();
    });
    authorizationConfirm?.addEventListener('click', ()  => {
        if (!authorizerRut?.reportValidity() || !authorizerPassword?.reportValidity()) return;

        authorizationConfirmed = true;
        authorizationDialog.close();
        dispensationForm?.requestSubmit(dispensationSubmit);
    });
    authorizationCancel?.addEventListener('click', closeAuthorizationDialog);
    authorizationDialog?.addEventListener('cancel', (event)  => {
        event.preventDefault();
        closeAuthorizationDialog();
    });
    if (authorizationDialog?.dataset.openOnLoad === 'true' && hasControlledProduct()) {
        openAuthorizationDialog();
    }
    dispensationRows.render();
    applyPrescription();

    document.addEventListener('dispensation-form:refresh', applyPrescription);
};
