<dialog class="delivery-order-dialog permission-confirmation-dialog" data-permission-confirmation aria-labelledby="permission-confirmation-title" aria-describedby="permission-confirmation-description">
    <header class="delivery-order-dialog-header">
        <span class="delivery-order-dialog-icon" aria-hidden="true"><img src="{{ asset('logo.jpg') }}" alt=""></span>
        <div>
            <h2 id="permission-confirmation-title">Confirmar permisos</h2>
            <p data-confirmation-role></p>
        </div>
        <button class="delivery-order-dialog-close" type="button" data-cancel-permission-confirmation aria-label="Cancelar y volver a revisar">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </header>
    <div class="delivery-order-dialog-body permission-confirmation-body">
        <div class="permission-confirmation-intro">
            <span class="permission-confirmation-intro-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
            <div>
                <strong data-confirmation-heading>Revisa los permisos antes de continuar</strong>
                <p id="permission-confirmation-description">Los cambios solo se aplicarán después de confirmar.</p>
            </div>
        </div>
        <ul class="permission-confirmation-list" data-confirmation-list></ul>
        <p class="permission-confirmation-error" data-confirmation-error role="alert" hidden></p>
    </div>
    <footer class="delivery-order-dialog-footer permission-confirmation-footer">
        <span data-confirmation-summary></span>
        <div class="delivery-order-dialog-actions">
            <button class="btn btn-outline" type="button" data-cancel-permission-confirmation>Volver a revisar</button>
            <button class="btn btn-primary" type="button" data-confirm-permission-changes>Confirmar y guardar</button>
        </div>
    </footer>
</dialog>
