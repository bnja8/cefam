const MAX_DATE = '9999-12-31';
const MAX_DATE_TIME = '9999-12-31T23:59';

const constrainDateInput = (input) => {
    const maximum = input.type === 'datetime-local' ? MAX_DATE_TIME : MAX_DATE;
    if (!input.max || input.max > maximum) input.max = maximum;
};

export const initializeDateInputs = () => {
    document.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach(constrainDateInput);

    document.addEventListener('focusin', (event) => {
        const input = event.target;
        if (input instanceof HTMLInputElement && ['date', 'datetime-local'].includes(input.type)) constrainDateInput(input);
    });
};
