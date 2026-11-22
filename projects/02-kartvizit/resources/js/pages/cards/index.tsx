import { Head, Link } from '@inertiajs/react';
import { Check, Copy, Eye, ExternalLink, Pencil, Plus, Sparkles, Trash2 } from 'lucide-react';
import CardController from '@/actions/App/Http/Controllers/CardController';
import PlanController from '@/actions/App/Http/Controllers/PlanController';
import { themes } from '@/components/card-preview';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card as UiCard, CardContent } from '@/components/ui/card';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';
import type { Card, PlanInfo } from '@/types';

type Props = {
    cards: Card[];
    plan: PlanInfo;
    canCreate: boolean;
    limitMessage: string | null;
};

export default function CardsIndex({ cards, plan, canCreate, limitMessage }: Props) {
    const [copied, copy] = useClipboard();

    return (
        <>
            <Head title="Kartlarım" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Kartlarım"
                        description={`${plan.label} plan · ${cards.length} / ${plan.card_limit} kart kullanılıyor`}
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={CardController.create()}>
                                <Plus /> Yeni kart
                            </Link>
                        </Button>
                    )}
                </div>

                {!canCreate && limitMessage && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                        <span>{limitMessage}</span>
                        <Button size="sm" asChild>
                            <Link href={PlanController.show()}>
                                <Sparkles /> Planları incele
                            </Link>
                        </Button>
                    </div>
                )}

                {cards.length === 0 && (
                    <div className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        Henüz bir kartınız yok. İlk dijital kartvizitinizi oluşturun.
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {cards.map((card) => (
                        <UiCard key={card.id} className="overflow-hidden py-0">
                            <div className={cn('flex items-center gap-3 p-4', themes[card.theme].page)}>
                                <div className="flex size-12 items-center justify-center overflow-hidden rounded-full bg-black/10 font-semibold">
                                    {card.avatar_url ? (
                                        <img src={card.avatar_url} alt="" className="size-full object-cover" />
                                    ) : (
                                        card.full_name[0]
                                    )}
                                </div>
                                <div className="min-w-0">
                                    <p className="truncate font-semibold">{card.full_name}</p>
                                    <p className="truncate text-xs opacity-75">{card.title}</p>
                                </div>
                            </div>
                            <CardContent className="space-y-3 pb-4">
                                <div className="flex items-center gap-2 text-sm">
                                    <code className="truncate rounded bg-muted px-1.5 py-0.5 text-xs">/k/{card.slug}</code>
                                    {!card.is_published && <Badge variant="outline">Taslak</Badge>}
                                    <span className="ml-auto flex items-center gap-1 text-xs text-muted-foreground">
                                        <Eye className="size-3.5" /> {card.view_count}
                                    </span>
                                </div>
                                <div className="flex gap-1">
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={CardController.edit(card.id)}>
                                            <Pencil /> Düzenle
                                        </Link>
                                    </Button>
                                    <Button size="sm" variant="ghost" asChild>
                                        <a href={card.public_url} target="_blank" rel="noreferrer">
                                            <ExternalLink /> Aç
                                        </a>
                                    </Button>
                                    <Button size="sm" variant="ghost" onClick={() => copy(card.public_url)}>
                                        {copied === card.public_url ? <Check /> : <Copy />} Kopyala
                                    </Button>
                                    <Button size="icon" variant="ghost" className="ml-auto" aria-label="Sil" asChild>
                                        <Link
                                            href={CardController.destroy(card.id)}
                                            as="button"
                                            onBefore={() => confirm(`"${card.full_name}" kartı silinsin mi?`)}
                                        >
                                            <Trash2 className="text-destructive" />
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </UiCard>
                    ))}
                </div>
            </div>
        </>
    );
}

CardsIndex.layout = {
    breadcrumbs: [{ title: 'Kartlarım', href: CardController.index() }],
};
