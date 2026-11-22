const clearAutofilledFields = (form) => {
    form.querySelectorAll('[data-person-autofilled]').forEach((field) => {
        field.value = '';
        field.dispatchEvent(new Event('change', { bubbles: true }));
        delete field.dataset.personAutofilled;
    });
};

const fillFields = (form, record) => {
    for (const key of ['nombre', 'apellidoPaterno', 'apellidoMaterno', 'fecha_nacimiento', 'telefono', 'tipo_profesional', 'vinculo']) {
        const field = form.elements.namedItem(key);
        const value = record[key];
        if (!field || value === null || value === undefined) continue;

        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dataset.personAutofilled = 'true';
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }
};

export const initializePersonLookup = () => {
    document.querySelectorAll('[data-person-lookup]').forEach((form) => {
        const rutInput = form.querySelector('[data-person-rut]');
        const status = form.querySelector('[data-person-lookup-status]');
        const submit = form.querySelector('[type="submit"]');
        if (!rutInput || !status || !submit) return;

        let timer;
        let controller;
        let requestId = 0;
        const originalButtonDisabled = submit.disabled;

        rutInput.addEventListener('input', () => {
            window.clearTimeout(timer);
            controller?.abort();
            requestId += 1;
            clearAutofilledFields(form);
            rutInput.setCustomValidity('');
            submit.disabled = originalButtonDisabled;
            status.textContent = '';

            const rut = window.limpiarRut(rutInput.value);
            if (!window.validarRut(rut)) return;

            const currentRequestId = requestId;
            submit.disabled = true;
            status.textContent = 'Verificando RUT…';
            timer = window.setTimeout(async () => {
                controller = new AbortController();
                try {
                    const url = new URL(form.dataset.personLookupUrl, window.location.origin);
                    url.searchParams.set('rut', rut);
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    });
                    if (!response.ok) throw new Error('No se pudo verificar el RUT.');
                    const { records } = await response.json();
                    if (!form.isConnected || currentRequestId !== requestId) return;

                    const type = form.dataset.personType;
                    const duplicate = type !== 'personas_autorizadas' && Boolean(records[type]);
                    const record = records[type]
                        || records.profesionales_salud
                        || records.usuarios
                        || records.pacientes
                        || records.personas_autorizadas;
                    if (record) fillFields(form, record);

                    if (duplicate) {
                        submit.disabled = true;
                        rutInput.setCustomValidity('Este RUT ya está registrado en este módulo.');
                        status.textContent = 'Esta persona ya está registrada en este módulo; no se puede crear un registro duplicado.';
                    } else {
                        submit.disabled = originalButtonDisabled;
                        status.textContent = record
                            ? 'Datos encontrados y completados. Verifica la información antes de guardar.'
                            : 'No se encontraron datos registrados para este RUT.';
                    }
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    console.error(error);
                    if (currentRequestId !== requestId) return;
                    submit.disabled = originalButtonDisabled;
                    status.textContent = 'No fue posible verificar el RUT. El servidor validará el registro antes de guardarlo.';
                }
            }, 300);
        });
    });
};
