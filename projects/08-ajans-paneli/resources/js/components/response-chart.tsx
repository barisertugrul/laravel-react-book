import { healthStyles } from '@/components/health-badge';
import { formatDateTime, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SiteCheck } from '@/types';

/**
 * Son kontrollerin yanıt süreleri: her kontrol bir çubuk, rengi o kontrolün durumu.
 * Grafik kütüphanesi eklemeden, yalnızca flex ve yükseklik yüzdesiyle.
 */
export default function ResponseChart({ checks }: { checks: SiteCheck[] }) {
    const ordered = [...checks].reverse(); // eskiden yeniye
    const max = Math.max(500, ...ordered.map((c) => c.response_ms ?? 0));

    if (ordered.length === 0) {
        return <p className="py-8 text-center text-sm text-muted-foreground">Henüz kontrol yok.</p>;
    }

    return (
        <div>
            {/* overflow-hidden + min-w-0: 48 çubuk dar kutuya sığsın; max-w: tek kontrolde dev bir blok olmasın */}
            <div className="flex h-32 items-end gap-px overflow-hidden" role="img" aria-label="Yanıt süresi grafiği">
                {ordered.map((check) => (
                    <div
                        key={check.id}
                        title={`${formatDateTime(check.checked_at)} · ${check.response_ms === null ? 'yanıt yok' : `${check.response_ms} ms`}`}
                        className={cn('max-w-4 min-w-0 flex-1 rounded-t-sm', healthStyles[check.status].dot, check.response_ms === null && 'opacity-40')}
                        // Yanıt alınamayan kontrol tam boy, soluk çubuk olarak görünür
                        style={{ height: `${check.response_ms === null ? 100 : Math.max(4, (check.response_ms / max) * 100)}%` }}
                    />
                ))}
            </div>
            <div className="mt-1 flex justify-between text-xs text-muted-foreground tabular-nums">
                <span>{formatTime(ordered[0].checked_at)}</span>
                <span>en yüksek {max} ms</span>
                <span>{formatTime(ordered[ordered.length - 1].checked_at)}</span>
            </div>
        </div>
    );
}
