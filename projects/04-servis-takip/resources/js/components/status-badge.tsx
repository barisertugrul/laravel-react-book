import { cn } from '@/lib/utils';
import type { TicketStatus } from '@/types';

export const statusLabels: Record<TicketStatus, string> = {
    received: 'Teslim alındı',
    diagnosing: 'Arıza tespiti',
    waiting_approval: 'Onay bekleniyor',
    repairing: 'Tamir ediliyor',
    ready: 'Teslime hazır',
    delivered: 'Teslim edildi',
    cancelled: 'İptal edildi',
};

export const statusColors: Record<TicketStatus, string> = {
    received: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    diagnosing: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    waiting_approval: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    repairing: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
    ready: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    delivered: 'bg-neutral-200 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200',
};

export default function StatusBadge({ status, className }: { status: TicketStatus; className?: string }) {
    return (
        <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap', statusColors[status], className)}>
            {statusLabels[status]}
        </span>
    );
}
