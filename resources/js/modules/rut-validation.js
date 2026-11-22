// Valida y normaliza los RUT ingresados en formularios.
const cleanRut = (rut) /* Conserva solo los caracteres válidos del RUT. */ => String(rut ?? '').replace(/[\s.-]/g, '').toUpperCase();

// Comprueba el formato y el dígito verificador del RUT.
const isValidRut = (rut) => {
    const normalizedRut = cleanRut(rut);
    if (!/^\d{7,8}[0-9K]$/.test(normalizedRut)) return false;

    const body = normalizedRut.slice(0, -1);
    let sum = 0;
    let weight = 2;
    for (let index = body.length - 1; index >= 0; index -= 1) {
        sum += Number(body[index]) * weight;
        weight = weight === 7 ? 2 : weight + 1;
    }
    const modulo = sum % 11;
    const verifier = modulo === 0 ? '0' : modulo === 1 ? 'K' : String(11 - modulo);
    return verifier === normalizedRut.slice(-1);
};

// Activa la validación de RUT en los formularios.
export const initializeRutValidation = () => {
    window.limpiarRut = cleanRut;
    window.validarRut = isValidRut;

    document.querySelectorAll('[data-rut-input]').forEach((input)  => {
        const error = document.getElementById(input.getAttribute('aria-describedby'));
        // Valida el campo y actualiza sus mensajes de accesibilidad.
        const validateRutField = () => {
            const value = input.value.trim();
            const valid = value === '' ? !input.required : isValidRut(value);
            input.setCustomValidity(valid ? '' : 'El RUT ingresado no es válido.');
            input.setAttribute('aria-invalid', valid ? 'false' : 'true');
            if (error) error.textContent = valid ? '' : 'Ingresa un RUT válido, incluyendo su dígito verificador.';
            return valid;
        };

        input.addEventListener('input', validateRutField);
        input.addEventListener('blur', ()  => {
            if (isValidRut(input.value)) input.value = cleanRut(input.value);
        });
        input.form?.addEventListener('submit', (event)  => {
            if (!validateRutField()) {
                event.preventDefault();
                input.reportValidity();
                input.focus();
            } else {
                input.value = cleanRut(input.value);
            }
        });
    });
};
