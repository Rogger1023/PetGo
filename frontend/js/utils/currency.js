const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
});

export function formatPrice(priceCents) {
    return currencyFormatter.format(priceCents / 100);
}