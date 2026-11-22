import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * Laravel'in ürettiği sayfa bağlantılarını gösterir.
 * Etiketler (&laquo; Önceki) lang/tr/pagination.php'den gelir ve HTML varlık içerir.
 */
export default function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center justify-between gap-2 text-sm">
            <span className="text-muted-foreground">
                {page.total} kayıttan {page.from}–{page.to} arası
            </span>
            <div className="flex gap-1">
                {page.links.map((link, i) =>
                    link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={cn('rounded-md border px-3 py-1', link.active ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-accent')}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span key={i} className="rounded-md border px-3 py-1 opacity-40" dangerouslySetInnerHTML={{ __html: link.label }} />
                    ),
                )}
            </div>
        </nav>
    );
}
