const tz = { timeZone: 'Europe/Istanbul' } as const;

export const formatDateTime = (iso: string) => new Intl.DateTimeFormat('tr-TR', { ...tz, dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));

export const formatDate = (iso: string) => new Intl.DateTimeFormat('tr-TR', { ...tz, dateStyle: 'medium' }).format(new Date(iso));

export const formatTime = (iso: string) => new Intl.DateTimeFormat('tr-TR', { ...tz, timeStyle: 'short' }).format(new Date(iso));

/** "3 dk önce", "2 sa önce", "5 gün önce" */
export function timeAgo(iso: string | null): string {
    if (!iso) {
        return 'henüz yok';
    }

    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
    const rtf = new Intl.RelativeTimeFormat('tr-TR', { numeric: 'auto', style: 'short' });

    if (seconds < 60) return 'az önce';
    if (seconds < 3600) return rtf.format(-Math.floor(seconds / 60), 'minute');
    if (seconds < 86400) return rtf.format(-Math.floor(seconds / 3600), 'hour');

    return rtf.format(-Math.floor(seconds / 86400), 'day');
}

/** SSL bitişine kalan gün; geçmişse negatif */
export const daysUntil = (iso: string) => Math.floor((new Date(iso).getTime() - Date.now()) / 86_400_000);

export const hostOf = (url: string) => url.replace(/^https?:\/\//, '').replace(/\/$/, '');
