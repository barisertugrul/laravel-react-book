import { Head, Link } from '@inertiajs/react';
import { Download, EyeOff } from 'lucide-react';
import CardController from '@/actions/App/Http/Controllers/CardController';
import PublicCardController from '@/actions/App/Http/Controllers/PublicCardController';
import CardPreview, { themes } from '@/components/card-preview';
import { cn } from '@/lib/utils';
import type { Card } from '@/types';

export default function PublicCard({ card, qrSvg, isOwner }: { card: Card; qrSvg: string; isOwner: boolean }) {
    const theme = themes[card.theme];

    return (
        <>
            <Head title={card.full_name} />
            <div className={cn('min-h-screen', theme.page)}>
                {isOwner && (
                    <div className="flex items-center justify-center gap-3 bg-black/80 px-4 py-2 text-sm text-white">
                        {!card.is_published && (
                            <span className="flex items-center gap-1"><EyeOff className="size-4" /> Bu kart yayında değil, yalnızca siz görüyorsunuz.</span>
                        )}
                        <Link href={CardController.edit(card.id)} className="underline">Düzenle</Link>
                    </div>
                )}
                <main className="mx-auto max-w-md">
                    <CardPreview card={card} className="bg-none bg-transparent">
                        <a
                            href={PublicCardController.vcard(card.slug).url}
                            className={cn('flex w-full max-w-xs items-center justify-center gap-2 rounded-full py-3 text-sm font-semibold transition', theme.accent)}
                        >
                            <Download className="size-4" /> Rehbere ekle
                        </a>
                        <div className="mt-4 flex flex-col items-center gap-2">
                            {/* Sunucunun ürettiği SVG; kullanıcı girdisi içermez */}
                            <div className="rounded-xl bg-white p-2" dangerouslySetInnerHTML={{ __html: qrSvg }} />
                            <p className={cn('text-xs', theme.muted)}>Kartı paylaşmak için okutun</p>
                        </div>
                    </CardPreview>
                </main>
            </div>
        </>
    );
}
