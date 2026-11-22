import { Globe, Mail, MapPin, Phone } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { CardLink, ThemeName } from '@/types';

/** Her temanın renkleri tek bir yerde tanımlı. Yeni tema eklemek = buraya bir satır. */
export const themes: Record<ThemeName, { label: string; page: string; accent: string; muted: string }> = {
    klasik: {
        label: 'Klasik',
        page: 'bg-white text-stone-900',
        accent: 'bg-stone-900 text-white hover:bg-stone-700',
        muted: 'text-stone-500',
    },
    gece: {
        label: 'Gece',
        page: 'bg-slate-900 text-white',
        accent: 'bg-amber-400 text-slate-900 hover:bg-amber-300',
        muted: 'text-slate-400',
    },
    okyanus: {
        label: 'Okyanus',
        page: 'bg-gradient-to-b from-sky-500 to-indigo-700 text-white',
        accent: 'bg-white text-indigo-700 hover:bg-indigo-50',
        muted: 'text-sky-100',
    },
};

export type CardPreviewData = {
    full_name: string;
    title?: string | null;
    company?: string | null;
    bio?: string | null;
    phone?: string | null;
    email?: string | null;
    website?: string | null;
    address?: string | null;
    avatar_url?: string | null;
    theme: ThemeName;
    links?: CardLink[];
};

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toLocaleUpperCase('tr-TR'))
        .join('');
}

/**
 * Kartın görünümü. Hem herkese açık sayfada hem de düzenleme ekranındaki
 * canlı önizlemede aynı bileşen kullanılır; ikisi asla birbirinden farklı görünmez.
 */
export default function CardPreview({
    card,
    className,
    children,
}: {
    card: CardPreviewData;
    className?: string;
    children?: ReactNode;
}) {
    const theme = themes[card.theme] ?? themes.klasik;
    const subtitle = [card.title, card.company].filter(Boolean).join(' · ');
    const actions = [
        card.phone && { icon: Phone, label: 'Ara', href: `tel:${card.phone.replace(/\s/g, '')}` },
        card.email && { icon: Mail, label: 'E-posta', href: `mailto:${card.email}` },
        card.website && { icon: Globe, label: 'Web', href: card.website },
    ].filter(Boolean) as { icon: typeof Phone; label: string; href: string }[];

    return (
        <div className={cn('flex flex-col items-center gap-5 px-6 py-10 text-center', theme.page, className)}>
            <div className="flex size-28 items-center justify-center overflow-hidden rounded-full bg-black/10 text-3xl font-semibold ring-4 ring-white/40">
                {card.avatar_url ? (
                    <img src={card.avatar_url} alt={card.full_name} className="size-full object-cover" />
                ) : (
                    initials(card.full_name || '?')
                )}
            </div>

            <div>
                <h1 className="text-2xl font-semibold">{card.full_name || 'Ad Soyad'}</h1>
                {subtitle && <p className={cn('mt-1 text-sm', theme.muted)}>{subtitle}</p>}
            </div>

            {card.bio && <p className="max-w-xs text-sm leading-relaxed">{card.bio}</p>}

            {actions.length > 0 && (
                <div className="flex gap-3">
                    {actions.map((action) => (
                        <a
                            key={action.label}
                            href={action.href}
                            className={cn('flex w-20 flex-col items-center gap-1 rounded-xl py-3 text-xs font-medium transition', theme.accent)}
                        >
                            <action.icon className="size-5" />
                            {action.label}
                        </a>
                    ))}
                </div>
            )}

            {card.links && card.links.length > 0 && (
                <div className="flex w-full max-w-xs flex-col gap-2">
                    {card.links.map((link, i) => (
                        <a
                            key={i}
                            href={link.url}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-full border border-current/25 px-4 py-2.5 text-sm font-medium transition hover:bg-black/5"
                        >
                            {link.label || 'Bağlantı'}
                        </a>
                    ))}
                </div>
            )}

            {card.address && (
                <p className={cn('flex items-center gap-1 text-xs', theme.muted)}>
                    <MapPin className="size-3.5" /> {card.address}
                </p>
            )}

            {children}
        </div>
    );
}
