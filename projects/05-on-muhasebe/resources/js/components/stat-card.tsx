import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export default function StatCard({
    label,
    value,
    hint,
    tone,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    tone?: 'danger';
}) {
    return (
        <div className="rounded-xl border p-4">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div
                className={cn(
                    'mt-1 text-2xl font-semibold tabular-nums',
                    tone === 'danger' && 'text-red-600 dark:text-red-400',
                )}
            >
                {value}
            </div>
            {hint && (
                <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
            )}
        </div>
    );
}
