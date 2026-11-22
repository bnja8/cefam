export const initializePatientSearch = () /* Callback que procesa el paso actual. */ => {
    document.querySelectorAll('[data-patient-search-url]').forEach((input)  => {
        const patientValue = document.getElementById(input.dataset.patientValueTarget);
        const results = document.getElementById(input.getAttribute('aria-controls'));
        if (!patientValue || !results) return;
        const help = document.getElementById(`${input.id}-help`);
        const selectedRutOnly = input.dataset.patientSelectedRutOnly === 'true';
        const defaultHelpText = help?.textContent ?? '';
        const selectedHelpText = 'Paciente seleccionado. Para cambiarlo, busca otro y selecciónalo.';
        const updateHelpText = (text) => {
            if (help) help.textContent = text;
        };

        let timer;
        let controller;
        let activeIndex = -1;

        const closeResults = () => {
            results.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
        };
        const showMessage = (message) => {
            results.replaceChildren();
            const element = document.createElement('div');
            element.className = 'search-result-message';
            element.setAttribute('role', 'status');
            element.textContent = message;
            results.append(element);
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };
        const choosePatient = (patient) => {
            input.value = selectedRutOnly ? patient.rut : `${patient.nombre_completo} · ${patient.rut}`;
            patientValue.value = patient.rut;
            input.setCustomValidity('');
            updateHelpText(selectedHelpText);
            closeResults();
            patientValue.dispatchEvent(new Event('change', { bubbles: true }));
        };

        input.addEventListener('input', ()  => {
            updateHelpText(defaultHelpText);
            patientValue.value = '';
            patientValue.dispatchEvent(new Event('change', { bubbles: true }));
            input.setCustomValidity(input.required || input.value.trim() ? 'Selecciona un paciente de los resultados.' : '');
            controller?.abort();
            window.clearTimeout(timer);

            const term = input.value.trim();
            if (term.length < 2) {
                closeResults();
                return;
            }

            timer = window.setTimeout(async () /* Ejecuta la acción al finalizar la espera. */ => {
                controller = new AbortController();
                showMessage('Buscando pacientes…');
                try {
                    const url = new URL(input.dataset.patientSearchUrl, window.location.origin);
                    url.searchParams.set('q', term);
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    });
                    if (!response.ok) throw new Error('No se pudo completar la búsqueda de pacientes.');
                    const patients = await response.json();
                    if (!input.isConnected || input.value.trim() !== term) return;
                    results.replaceChildren();
                    if (patients.length === 0) {
                        showMessage('No se encontraron pacientes activos.');
                        return;
                    }

                    patients.forEach((patient, index)  => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'search-result patient-search-result';
                        option.id = `${input.id}-option-${index}`;
                        option.setAttribute('role', 'option');
                        option.setAttribute('aria-selected', 'false');
                        const name = document.createElement('strong');
                        name.textContent = patient.nombre_completo;
                        const rut = document.createElement('small');
                        rut.textContent = patient.rut;
                        option.append(name, rut);
                        option.addEventListener('click', ()  => choosePatient(patient));
                        results.append(option);
                    });
                    results.hidden = false;
                    input.setAttribute('aria-expanded', 'true');
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    console.error(error);
                    showMessage('No se pudo buscar. Intenta nuevamente.');
                }
            }, 250);
        });

        input.addEventListener('keydown', (event)  => {
            const options = [...results.querySelectorAll('[role="option"]')];
            if (event.key === 'Escape') {
                closeResults();
                return;
            }
            if (options.length === 0 || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
            if (event.key === 'Enter') {
                event.preventDefault();
                options[activeIndex >= 0 ? activeIndex : 0].click();
                return;
            }

            event.preventDefault();
            activeIndex = (activeIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index)  => option.setAttribute('aria-selected', String(index === activeIndex)));
            input.setAttribute('aria-activedescendant', options[activeIndex].id);
        });

        document.addEventListener('click', (event)  => {
            if (!event.target.closest('.patient-picker-control')) closeResults();
        });
        if (patientValue.value) {
            input.setCustomValidity('');
            updateHelpText(selectedHelpText);
        }
    });
};
