const localMobileDigits = (value) => {
    let digits = value.replace(/\D/g, '');
    if (digits.startsWith('569') && digits.length === 11) digits = digits.slice(3);
    else if (digits.startsWith('9') && digits.length === 9) digits = digits.slice(1);
    return digits.slice(0, 8);
};

export const initializeChileanMobileInputs = () => {
    document.querySelectorAll('[data-chilean-mobile-phone]').forEach((input) => {
        input.addEventListener('beforeinput', (event) => {
            if (event.inputType.startsWith('insert') && event.data && /\D/.test(event.data)) {
                event.preventDefault();
            }
        });
        input.addEventListener('input', () => {
            const digits = localMobileDigits(input.value);
            if (input.value !== digits) input.value = digits;
        });
    });
};
