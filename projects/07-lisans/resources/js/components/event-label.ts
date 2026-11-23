import type { LicenseEvent, LicenseEventType } from '@/types';

const labels: Record<LicenseEventType, string> = {
    issued: 'Lisans üretildi',
    activated: 'Sitede etkinleştirildi',
    reactivated: 'Yeniden etkinleştirildi',
    deactivated: 'Site lisansı kaldırdı',
    removed: 'Müşteri siteyi kaldırdı',
    rejected: 'Aktivasyon reddedildi',
    revoked: 'Lisans iptal edildi',
    extended: 'Güncelleme süresi uzatıldı',
};

const reasons: Record<string, string> = { revoked: 'lisans iptal edilmiş', site_limit: 'site sınırı dolu' };

/** "Aktivasyon reddedildi · site sınırı dolu" */
export function eventLabel(event: LicenseEvent): string {
    const reason = event.meta?.reason;

    return reason && event.type === 'rejected' ? `${labels[event.type]} · ${reasons[reason] ?? reason}` : labels[event.type];
}
