import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { InvoiceStatus } from '@/types';

const styles: Record<InvoiceStatus, { label: string; className: string }> = {
    open: {
        label: 'Açık',
        className:
            'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
    },
    partial: {
        label: 'Kısmen ödendi',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    },
    paid: {
        label: 'Ödendi',
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    },
    overdue: {
        label: 'Vadesi geçti',
        className:
            'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    },
    cancelled: {
        label: 'İptal',
        className: 'bg-muted text-muted-foreground line-through',
    },
};

export default function InvoiceStatusBadge({
    status,
}: {
    status: InvoiceStatus;
}) {
    return (
        <Badge
            variant="secondary"
            className={cn('border-transparent', styles[status].className)}
        >
            {styles[status].label}
        </Badge>
    );
}
