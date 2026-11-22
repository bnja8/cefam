export const formatQuantity = (quantity) /* Callback que procesa el paso actual. */ => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 })
    .format(Number(quantity));
