const initializeAdjustmentConfirmation = (dialog) => {
    const form = dialog.querySelector('[data-adjustment-resolution-form]');
    const decisionInput = dialog.querySelector('[data-adjustment-decision]');
    const title = dialog.querySelector('[data-adjustment-confirmation-title]');
    const message = dialog.querySelector('[data-adjustment-confirmation-message]');
    const confirmButton = dialog.querySelector('[data-adjustment-confirm-button]');
    const rejectionReason = dialog.querySelector('[data-adjustment-rejection-reason]');
    const rejectionInput = dialog.querySelector('[data-adjustment-rejection-input]');

    if (!form || !decisionInput || !title || !message || !confirmButton || !rejectionReason || !rejectionInput) return;

    const setDecision = (decision) => {
        const rejecting = decision === 'RECHAZAR';
        decisionInput.value = decision;
        title.textContent = rejecting ? 'Confirmar rechazo de solicitud' : 'Confirmar solicitud';
        message.textContent = rejecting
            ? 'Al confirmar, se rechazará la solicitud completa. Indica el motivo del rechazo.'
            : 'Al confirmar, se aprobarán todas las líneas de esta solicitud y se actualizarán las existencias.';
        confirmButton.textContent = rejecting ? 'Confirmar rechazo' : 'Confirmar solicitud';
        rejectionReason.hidden = !rejecting;
        rejectionInput.disabled = !rejecting;
        rejectionInput.required = rejecting;
        dialog.dataset.decision = decision;
    };

    const open = (decision) => {
        setDecision(decision);
        if (typeof dialog.showModal !== 'function') {
            console.error('Este navegador no admite ventanas de diálogo modales para resolver ajustes.');
            window.alert('No se pudo abrir la ventana de confirmación. Actualiza el navegador antes de continuar.');
            return;
        }
        if (!dialog.open) dialog.showModal();
        if (decision === 'RECHAZAR') rejectionInput.focus();
    };

    document.querySelectorAll('[data-open-adjustment-confirmation]').forEach((button) => {
        button.addEventListener('click', () => open(button.dataset.openAdjustmentConfirmation));
    });
    dialog.querySelectorAll('[data-close-adjustment-confirmation]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    if (dialog.dataset.openOnLoad === 'true') {
        open(dialog.dataset.initialDecision === 'RECHAZAR' ? 'RECHAZAR' : 'APROBAR');
    } else {
        setDecision('APROBAR');
    }
};

export const initializeInventoryAdjustmentConfirmation = () => {
    document.querySelectorAll('[data-adjustment-confirmation]').forEach(initializeAdjustmentConfirmation);
};
