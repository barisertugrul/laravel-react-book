import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { HealthStatus } from '@/types';

export const healthStyles: Record<HealthStatus, { label: string; className: string; dot: string }> = {
    ok: { label: 'Sağlıklı', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200', dot: 'bg-emerald-500' },
    warning: { label: 'Uyarı', className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200', dot: 'bg-amber-500' },
    critical: { label: 'Kritik', className: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200', dot: 'bg-red-500' },
    unknown: { label: 'Bilinmiyor', className: 'bg-muted text-muted-foreground', dot: 'bg-neutral-400' },
};

export default function HealthBadge({ status, className }: { status: HealthStatus; className?: string }) {
    const style = healthStyles[status];

    return (
        <Badge variant="secondary" className={cn('border-transparent', style.className, className)}>
            <span className={cn('size-1.5 rounded-full', style.dot)} />
            {style.label}
        </Badge>
    );
}
