import { cn } from '@/lib/utils';

const palettes = ['bg-amber-200 text-amber-900', 'bg-orange-200 text-orange-900', 'bg-stone-300 text-stone-800', 'bg-emerald-200 text-emerald-900', 'bg-rose-200 text-rose-900', 'bg-sky-200 text-sky-900'];

/**
 * Ürün görseli yerine ürün adının baş harfleriyle renkli bir kutu.
 * Görsel yükleme Bölüm 1'de anlatıldı; burada alıştırmaya bırakıyoruz.
 */
export default function ProductImage({ product, className }: { product: { id: number; name: string }; className?: string }) {
    const initials = product.name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toLocaleUpperCase('tr-TR');

    return (
        <div className={cn('flex aspect-square items-center justify-center rounded-lg text-3xl font-semibold', palettes[product.id % palettes.length], className)}>
            {initials}
        </div>
    );
}
