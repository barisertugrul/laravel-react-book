const formatter = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });

/** 125050 → "₺1.250,50" (tutarlar kuruş cinsinden tam sayı) */
export const formatMoney = (cents: number) => formatter.format(cents / 100);
