/**
 * Tarih ve saat yardımcıları. Sunucu saatleri UTC olarak gönderir (…Z);
 * biz her zaman işletmenin saat diliminde gösteriyoruz. Böylece yurt dışındaki
 * bir müşteri de randevu saatini işletmenin saatiyle görür.
 */
export const TIME_ZONE = 'Europe/Istanbul';

const tr = (options: Intl.DateTimeFormatOptions) =>
    new Intl.DateTimeFormat('tr-TR', { timeZone: TIME_ZONE, ...options });

export const formatTime = (iso: string) => tr({ hour: '2-digit', minute: '2-digit' }).format(new Date(iso));

export const formatLongDate = (iso: string) =>
    tr({ day: 'numeric', month: 'long', year: 'numeric', weekday: 'long' }).format(new Date(iso));

/** "2026-11-23" gibi bir günü (saat dilimi kaymadan) "23 Kas Pzt" biçiminde gösterir. */
export function formatDay(date: string, options: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', weekday: 'short' }) {
    return new Intl.DateTimeFormat('tr-TR', { timeZone: 'UTC', ...options }).format(new Date(`${date}T00:00:00Z`));
}

/** Bir ISO zamanın işletme saat dilimindeki günü ve gece yarısından itibaren dakikası. */
export function localParts(iso: string): { date: string; minutes: number } {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone: TIME_ZONE,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(new Date(iso))
            .map((p) => [p.type, p.value]),
    );

    return {
        date: `${parts.year}-${parts.month}-${parts.day}`,
        minutes: Number(parts.hour) * 60 + Number(parts.minute),
    };
}

/** "2026-11-23" + n gün → "2026-11-24" */
export function addDays(date: string, days: number): string {
    const d = new Date(`${date}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
}

export const formatPrice = (value: string | number) =>
    new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(Number(value));
