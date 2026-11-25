const formatter = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });

/** 125050 → "₺1.250,50" (tutarlar kuruş cinsinden tam sayı) */
export const formatMoney = (cents: number) => formatter.format(cents / 100);

/** Kuruşu forma yazılacak metne çevirir: 125050 → "1250,50" (Bölüm 6'daki gibi) */
export const toMoneyInput = (cents: number) => `${Math.trunc(cents / 100)},${String(cents % 100).padStart(2, '0')}`;
