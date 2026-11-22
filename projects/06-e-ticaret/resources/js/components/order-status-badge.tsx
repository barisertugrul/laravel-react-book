import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { OrderStatus } from '@/types';

const styles: Record<OrderStatus, { label: string; className: string }> = {
    pending: { label: 'Ödeme bekleniyor', className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' },
    paid: { label: 'Hazırlanıyor', className: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200' },
    shipped: { label: 'Kargoda', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' },
    cancelled: { label: 'İptal edildi', className: 'bg-muted text-muted-foreground' },
};

export default function OrderStatusBadge({ status }: { status: OrderStatus }) {
    return (
        <Badge variant="secondary" className={cn('border-transparent', styles[status].className)}>
            {styles[status].label}
        </Badge>
    );
}

export const statusLabel = (status: OrderStatus) => styles[status].label;
