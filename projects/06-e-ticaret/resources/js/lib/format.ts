/** Sunucudan gelen UTC zaman damgasını Türkiye saatiyle gösterir */
export const formatDateTime = (iso: string) =>
    new Intl.DateTimeFormat('tr-TR', { timeZone: 'Europe/Istanbul', dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
