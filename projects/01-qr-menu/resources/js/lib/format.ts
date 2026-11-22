const tl = new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
});

/** "149.90" gibi bir değeri "₺149,90" biçiminde gösterir. */
export function formatPrice(value: string | number): string {
    return tl.format(Number(value));
}
