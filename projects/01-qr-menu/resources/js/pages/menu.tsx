import { Head } from '@inertiajs/react';
import { formatPrice } from '@/lib/format';
import type { Category } from '@/types';

/** QR kodu okutan müşterinin gördüğü sayfa. Önce mobil için tasarlandı. */
export default function Menu({
    restaurant,
    categories,
}: {
    restaurant: string;
    categories: Category[];
}) {
    return (
        <>
            <Head title="Menü" />
            <div className="min-h-screen bg-stone-50 text-stone-900">
                <header className="bg-stone-900 px-4 pt-8 pb-6 text-center text-white">
                    <p className="text-xs tracking-[0.3em] text-stone-400 uppercase">
                        Menü
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold">{restaurant}</h1>
                </header>

                {/* Kategoriler arasında hızlı geçiş: yatay kaydırılabilir şerit */}
                <nav className="sticky top-0 z-10 border-b bg-white/95 backdrop-blur">
                    <div className="mx-auto flex max-w-2xl gap-2 overflow-x-auto px-4 py-3">
                    {categories.map((category) => (
                        <a
                            key={category.id}
                            href={`#kategori-${category.id}`}
                            className="shrink-0 rounded-full border px-3 py-1 text-sm whitespace-nowrap hover:bg-stone-100"
                        >
                            {category.name}
                        </a>
                    ))}
                    </div>
                </nav>

                <main className="mx-auto max-w-2xl space-y-8 px-4 py-6">
                    {categories.map((category) => (
                        <section
                            key={category.id}
                            id={`kategori-${category.id}`}
                            className="scroll-mt-16"
                        >
                            <h2 className="mb-3 text-lg font-semibold">
                                {category.name}
                            </h2>
                            <ul className="space-y-3">
                                {category.products?.map((product) => (
                                    <li
                                        key={product.id}
                                        className="flex gap-3 rounded-xl bg-white p-3 shadow-sm"
                                    >
                                        {product.image_url && (
                                            <img
                                                src={product.image_url}
                                                alt={product.name}
                                                loading="lazy"
                                                className="size-20 shrink-0 rounded-lg object-cover"
                                            />
                                        )}
                                        <div className="flex flex-1 flex-col">
                                            <div className="flex items-start justify-between gap-2">
                                                <h3 className="font-medium">
                                                    {product.name}
                                                </h3>
                                                <span className="font-semibold whitespace-nowrap tabular-nums">
                                                    {formatPrice(product.price)}
                                                </span>
                                            </div>
                                            {product.description && (
                                                <p className="mt-1 text-sm text-stone-500">
                                                    {product.description}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}

                    {categories.length === 0 && (
                        <p className="text-center text-stone-500">
                            Menü yakında burada olacak.
                        </p>
                    )}
                </main>
            </div>
        </>
    );
}
