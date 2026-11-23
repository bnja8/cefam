const getPermissionChanges = (checkboxes) => {
    const added = [];
    const removed = [];

    checkboxes.forEach((checkbox) => {
        if (checkbox.disabled) return;

        const wasChecked = checkbox.dataset.initiallyChecked === 'true';
        if (checkbox.checked && !wasChecked) added.push(checkbox.dataset.permissionName);
        if (!checkbox.checked && wasChecked) removed.push(checkbox.dataset.permissionName);
    });

    return { added, removed };
};

const createConfirmationItem = (action, name, type) => {
    const item = document.createElement('li');
    item.className = `permission-confirmation-item is-${type}`;

    const icon = document.createElement('i');
    const iconName = type === 'grant' ? 'fa-circle-plus' : (type === 'remove' ? 'fa-circle-minus' : 'fa-circle-info');
    icon.className = `fa-solid ${iconName}`;
    icon.setAttribute('aria-hidden', 'true');

    const label = document.createElement('strong');
    label.textContent = action;

    const permission = document.createElement('span');
    permission.textContent = name;

    item.append(icon, label, permission);
    return item;
};

const initializePermissionForm = (form) => {
    const checkboxes = [...form.querySelectorAll('[data-permission-toggle]')];
    const changeSummary = form.querySelector('[data-change-summary]');
    const changeList = form.querySelector('[data-change-list]');
    const review = form.querySelector('[data-permission-review]');
    const roleNameInput = form.querySelector('[name="nombre"]');
    const dialog = form.querySelector('[data-permission-confirmation]');
    const dialogRole = dialog?.querySelector('[data-confirmation-role]');
    const dialogHeading = dialog?.querySelector('[data-confirmation-heading]');
    const dialogDescription = dialog?.querySelector('#permission-confirmation-description');
    const dialogList = dialog?.querySelector('[data-confirmation-list]');
    const dialogSummary = dialog?.querySelector('[data-confirmation-summary]');
    const dialogError = dialog?.querySelector('[data-confirmation-error]');
    const confirmButton = dialog?.querySelector('[data-confirm-permission-changes]');
    const submitButton = form.querySelector('button[type="submit"]');
    let confirmationApproved = false;

    const updateSummary = () => {
        if (!changeSummary || !changeList || !review) return;

        const { added, removed } = getPermissionChanges(checkboxes);
        const changes = [
            ...added.map((name) => ({ name, action: 'Se otorgará' })),
            ...removed.map((name) => ({ name, action: 'Se retirará' })),
        ];
        review.dataset.state = added.length > 0 ? 'grant' : (removed.length > 0 ? 'remove' : 'neutral');
        changeSummary.hidden = changes.length > 0;
        changeSummary.textContent = 'No hay cambios pendientes.';
        changeList.replaceChildren(...changes.map(({ action, name }) => {
            const item = document.createElement('li');
            item.textContent = `${action}: ${name}`;
            return item;
        }));
        changeList.hidden = changes.length === 0;
    };

    const prepareConfirmation = () => {
        if (!dialog || !dialogList || !confirmButton) return;

        const roleName = roleNameInput?.value.trim() || form.dataset.roleName || 'este rol';
        const { added, removed } = getPermissionChanges(checkboxes);
        const selected = checkboxes
            .filter((checkbox) => checkbox.checked && !checkbox.disabled)
            .map((checkbox) => checkbox.dataset.permissionName);
        const isCreating = form.dataset.permissionMode === 'create';
        const changes = isCreating
            ? selected.map((name) => ({ action: 'Se otorgará', name, type: 'grant' }))
            : [
                ...added.map((name) => ({ action: 'Se otorgará', name, type: 'grant' })),
                ...removed.map((name) => ({ action: 'Se retirará', name, type: 'remove' })),
            ];

        const reviewItems = changes.map(({ action, name, type }) => createConfirmationItem(action, name, type));
        if (isCreating && selected.length === 0) {
            reviewItems.push(createConfirmationItem(
                'Sin permisos de acceso',
                'Las cuentas con este rol no podrán abrir módulos hasta que se asignen permisos.',
                'empty',
            ));
        }
        dialogList.replaceChildren(...reviewItems);
        dialog.dataset.state = changes.some(({ type }) => type === 'grant')
            ? (changes.some(({ type }) => type === 'remove') ? 'mixed' : 'grant')
            : (changes.length > 0 ? 'remove' : 'empty');
        if (dialogRole) dialogRole.textContent = isCreating ? `Nuevo rol: ${roleName}` : `Rol: ${roleName}`;
        if (dialogHeading) {
            dialogHeading.textContent = isCreating
                ? (selected.length > 0 ? 'Permisos que recibirá este rol' : 'Se registrará un rol sin permisos')
                : 'Revisa los cambios solicitados';
        }
        if (dialogDescription) {
            dialogDescription.textContent = isCreating
                ? 'Confirma que estos accesos corresponden a las funciones de este rol.'
                : 'Solo se aplicarán los permisos que aparecen en esta lista.';
        }
        if (dialogSummary) {
            dialogSummary.textContent = isCreating
                ? (selected.length > 0
                    ? 'Se otorgarán los permisos que aparecen en la lista.'
                    : 'El rol se creará sin permisos de acceso.')
                : 'Se aplicarán únicamente los cambios que aparecen en la lista.';
        }
        confirmButton.textContent = isCreating
            ? (selected.length > 0 ? 'Crear rol y asignar permisos' : 'Crear rol sin permisos')
            : 'Confirmar y guardar';
        if (dialogError) dialogError.hidden = true;

        if (typeof dialog.showModal !== 'function') {
            const error = new Error('Este navegador no admite ventanas de diálogo modales.');
            console.error('No se pudo abrir la confirmación de permisos.', error);
            if (dialogError) {
                dialogError.textContent = 'No se pudo abrir la ventana de confirmación. Actualiza el navegador antes de guardar.';
                dialogError.hidden = false;
            }
            return;
        }

        try {
            dialog.showModal();
        } catch (error) {
            console.error('No se pudo abrir la confirmación de permisos.', error);
            if (dialogError) {
                dialogError.textContent = 'No se pudo abrir la ventana de confirmación. Intenta nuevamente.';
                dialogError.hidden = false;
            }
        }
    };

    form.addEventListener('submit', (event) => {
        if (confirmationApproved) {
            confirmationApproved = false;
            return;
        }
        const { added, removed } = getPermissionChanges(checkboxes);
        if (form.dataset.permissionMode === 'create' || added.length > 0 || removed.length > 0) {
            event.preventDefault();
            prepareConfirmation();
        }
    });
    form.addEventListener('change', updateSummary);
    dialog?.querySelectorAll('[data-cancel-permission-confirmation]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });
    confirmButton?.addEventListener('click', () => {
        if (!form.reportValidity()) return;
        if (typeof form.requestSubmit !== 'function') {
            if (dialogError) {
                dialogError.textContent = 'No se pudo enviar el formulario desde la ventana de confirmación. Actualiza el navegador.';
                dialogError.hidden = false;
            }
            return;
        }
        confirmationApproved = true;
        dialog.close();
        if (submitButton) form.requestSubmit(submitButton);
        else form.requestSubmit();
    });
    updateSummary();
};

export const initializePermissionEditor = () => {
    document.querySelectorAll('[data-permission-form]').forEach(initializePermissionForm);
};
