import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { LicenseStatus } from '@/types';

const styles: Record<LicenseStatus, { label: string; className: string }> = {
    active: { label: 'Etkin', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' },
    revoked: { label: 'İptal edildi', className: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' },
};

export default function LicenseStatusBadge({ status }: { status: LicenseStatus }) {
    return (
        <Badge variant="secondary" className={cn('border-transparent', styles[status].className)}>
            {styles[status].label}
        </Badge>
    );
}
