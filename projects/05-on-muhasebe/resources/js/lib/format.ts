/**
 * İki tür tarih alıyoruz:
 * - Gün: "2026-11-23" (fatura tarihi, vade). Saati yok; saat dilimine göre kaydırılmamalı.
 * - An: "2026-11-22T22:15:00.000000Z" (iptal zamanı). UTC'dir; Türkiye saatine çevrilmeli.
 */
export const formatDate = (date: string) =>
    date.length === 10
        ? new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(date + 'T00:00:00Z'))
        : new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'Europe/Istanbul' }).format(new Date(date));

/** "2026-11" → "Kas 2026" */
export const formatMonth = (month: string) =>
    new Intl.DateTimeFormat('tr-TR', { month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(month + '-01T00:00:00Z'));
