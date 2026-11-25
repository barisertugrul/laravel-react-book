const tz = { timeZone: 'Europe/Istanbul' } as const;

/** Sunucudan gelen UTC zaman damgasını Türkiye saatiyle gösterir */
export const formatDateTime = (iso: string) => new Intl.DateTimeFormat('tr-TR', { ...tz, dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));

/** "2027-11-23" veya ISO zaman damgası → "23 Kas 2027" */
export const formatDate = (value: string) => new Intl.DateTimeFormat('tr-TR', { ...tz, dateStyle: 'medium' }).format(new Date(value.length === 10 ? `${value}T12:00:00Z` : value));

/** null → "Sınırsız", 5 → "5 site" */
export const formatSites = (max: number | null) => (max === null ? 'Sınırsız site' : `${max} site`);
