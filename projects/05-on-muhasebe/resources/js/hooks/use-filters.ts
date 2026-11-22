import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Liste sayfalarındaki filtreleri adres çubuğuyla eşler.
 * Arama kutusu 300 ms bekledikten sonra istek atar (debounce).
 */
export function useFilters<F extends Record<string, string | undefined>>(
    url: string,
    filters: F,
) {
    const [search, setSearch] = useState(filters.q ?? '');
    const first = useRef(true);

    function apply(next: Partial<F>) {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...next }).filter(([, v]) => v),
        );
        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(
            () => apply({ q: search } as unknown as Partial<F>),
            300,
        );
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return { search, setSearch, apply };
}
