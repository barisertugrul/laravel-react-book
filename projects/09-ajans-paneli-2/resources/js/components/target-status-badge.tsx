import { Ban, CircleCheck, Clock, LoaderCircle, OctagonAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { TargetStatus } from '@/types';

const styles: Record<TargetStatus, { label: string; className: string; icon: typeof Clock }> = {
    pending: { label: 'Sırada', className: 'bg-muted text-muted-foreground', icon: Clock },
    running: { label: 'Çalışıyor', className: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200', icon: LoaderCircle },
    succeeded: { label: 'Tamam', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200', icon: CircleCheck },
    failed: { label: 'Hata', className: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200', icon: OctagonAlert },
    cancelled: { label: 'İptal', className: 'bg-muted text-muted-foreground', icon: Ban },
};

export default function TargetStatusBadge({ status }: { status: TargetStatus }) {
    const { label, className, icon: Icon } = styles[status];

    return (
        <Badge variant="secondary" className={cn('border-transparent', className)}>
            <Icon className={cn('size-3', status === 'running' && 'animate-spin')} />
            {label}
        </Badge>
    );
}
