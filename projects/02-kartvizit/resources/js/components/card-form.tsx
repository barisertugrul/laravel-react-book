import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Lock, Plus, Trash2 } from 'lucide-react';
import type { ComponentProps, FormEvent } from 'react';
import { useState } from 'react';
import CardController from '@/actions/App/Http/Controllers/CardController';
import CardPreview, { themes } from '@/components/card-preview';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { Card, CardLink, PlanInfo, ThemeName } from '@/types';

type CardFormData = {
    full_name: string;
    slug: string;
    title: string;
    company: string;
    bio: string;
    phone: string;
    email: string;
    website: string;
    address: string;
    theme: ThemeName;
    is_published: boolean;
    avatar: File | null;
    remove_avatar: boolean;
    links: CardLink[];
};

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export default function CardForm({ card, plan }: { card?: Card; plan: PlanInfo }) {
    // useForm, formun bütün verisini tek bir state'te tutar.
    // <Form> bileşeninden farkı: veriye her an erişebiliriz (canlı önizleme için gerekli).
    const form = useForm<CardFormData>({
        full_name: card?.full_name ?? '',
        slug: card?.slug ?? '',
        title: card?.title ?? '',
        company: card?.company ?? '',
        bio: card?.bio ?? '',
        phone: card?.phone ?? '',
        email: card?.email ?? '',
        website: card?.website ?? '',
        address: card?.address ?? '',
        theme: card?.theme ?? 'klasik',
        is_published: card?.is_published ?? true,
        avatar: null,
        remove_avatar: false,
        links: card?.links?.map(({ label, url }) => ({ label, url })) ?? [],
    });
    const { data, setData, processing } = form;
    // "links.0.url" gibi iç içe alan hatalarına erişmek için
    const errors = form.errors as Record<string, string | undefined>;

    const [avatarPreview, setAvatarPreview] = useState<string | null>(card?.avatar_url ?? null);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (card) {
            // Dosya içeren PUT isteği: POST + _method=PUT (metot taklidi)
            form.transform((formData) => ({ ...formData, _method: 'put' }));
            form.post(CardController.update.url(card.id), { forceFormData: true, preserveScroll: true });
        } else {
            form.post(CardController.store.url(), { forceFormData: true, preserveScroll: true });
        }
    }

    // --- Bağlantı listesi yardımcıları -------------------------------------
    const updateLink = (index: number, field: keyof CardLink, value: string) =>
        setData('links', data.links.map((link, i) => (i === index ? { ...link, [field]: value } : link)));

    const addLink = () => setData('links', [...data.links, { label: '', url: 'https://' }]);

    const removeLink = (index: number) => setData('links', data.links.filter((_, i) => i !== index));

    const moveLink = (index: number, direction: -1 | 1) => {
        const links = [...data.links];
        const target = index + direction;
        [links[index], links[target]] = [links[target], links[index]];
        setData('links', links);
    };

    const field = (name: keyof CardFormData, label: string, props: ComponentProps<typeof Input> = {}) => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                value={data[name] as string}
                onChange={(e) => setData(name, e.target.value)}
                {...props}
            />
            <InputError message={errors[name]} />
        </div>
    );

    return (
        <div className="grid gap-8 lg:grid-cols-[1fr_360px]">
            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <section className="space-y-4">
                    <h3 className="font-medium">Kimlik</h3>
                    {field('full_name', 'Ad soyad', { placeholder: 'Ayşe Demir', autoFocus: !card })}
                    <div className="grid gap-2">
                        <Label htmlFor="slug">Kart adresi</Label>
                        <div className="flex items-center rounded-md border border-input text-sm">
                            <span className="border-r bg-muted px-3 py-2 text-muted-foreground">
                                {window.location.host}/k/
                            </span>
                            <input
                                id="slug"
                                value={data.slug}
                                onChange={(e) => setData('slug', e.target.value.toLowerCase())}
                                placeholder="bos-birakirsaniz-addan-uretilir"
                                className="flex-1 bg-transparent px-3 py-2 outline-none"
                            />
                        </div>
                        <InputError message={errors.slug} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {field('title', 'Unvan', { placeholder: 'Kurucu' })}
                        {field('company', 'Şirket')}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="bio">Hakkında</Label>
                        <textarea
                            id="bio"
                            rows={3}
                            maxLength={300}
                            value={data.bio}
                            onChange={(e) => setData('bio', e.target.value)}
                            className={textareaClass}
                        />
                        <p className="text-right text-xs text-muted-foreground">{data.bio.length}/300</p>
                        <InputError message={errors.bio} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="avatar">Fotoğraf</Label>
                        <Input
                            id="avatar"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                setData('avatar', file);
                                setAvatarPreview(file ? URL.createObjectURL(file) : (card?.avatar_url ?? null));
                            }}
                        />
                        {form.progress && <progress value={form.progress.percentage} max={100} className="w-full" />}
                        <InputError message={errors.avatar} />
                        {card?.avatar_url && (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="remove_avatar"
                                    checked={data.remove_avatar}
                                    onCheckedChange={(checked) => {
                                        setData('remove_avatar', checked === true);
                                        setAvatarPreview(checked === true ? null : card.avatar_url);
                                    }}
                                />
                                <Label htmlFor="remove_avatar">Fotoğrafı kaldır</Label>
                            </div>
                        )}
                    </div>
                </section>

                <section className="space-y-4">
                    <h3 className="font-medium">İletişim</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {field('phone', 'Telefon', { type: 'tel', placeholder: '+90 5xx xxx xx xx' })}
                        {field('email', 'E-posta', { type: 'email' })}
                        {field('website', 'Web sitesi', { type: 'url', placeholder: 'https://' })}
                        {field('address', 'Adres', { placeholder: 'Kadıköy, İstanbul' })}
                    </div>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="font-medium">Bağlantılar</h3>
                        <Button type="button" variant="outline" size="sm" onClick={addLink} disabled={data.links.length >= 8}>
                            <Plus /> Bağlantı ekle
                        </Button>
                    </div>
                    {data.links.length === 0 && (
                        <p className="text-sm text-muted-foreground">Sosyal medya hesaplarınızı veya portfolyonuzu ekleyin.</p>
                    )}
                    {data.links.map((link, i) => (
                        <div key={i} className="space-y-1">
                            <div className="flex gap-2">
                                <Input
                                    aria-label="Bağlantı adı"
                                    className="w-36"
                                    placeholder="LinkedIn"
                                    value={link.label}
                                    onChange={(e) => updateLink(i, 'label', e.target.value)}
                                />
                                <Input
                                    aria-label="Bağlantı adresi"
                                    placeholder="https://"
                                    value={link.url}
                                    onChange={(e) => updateLink(i, 'url', e.target.value)}
                                />
                                <Button type="button" size="icon" variant="ghost" aria-label="Yukarı" disabled={i === 0} onClick={() => moveLink(i, -1)}>
                                    <ArrowUp />
                                </Button>
                                <Button type="button" size="icon" variant="ghost" aria-label="Aşağı" disabled={i === data.links.length - 1} onClick={() => moveLink(i, 1)}>
                                    <ArrowDown />
                                </Button>
                                <Button type="button" size="icon" variant="ghost" aria-label="Kaldır" onClick={() => removeLink(i)}>
                                    <Trash2 className="text-destructive" />
                                </Button>
                            </div>
                            <InputError message={errors[`links.${i}.label`] ?? errors[`links.${i}.url`]} />
                        </div>
                    ))}
                </section>

                <section className="space-y-3">
                    <h3 className="font-medium">Tema</h3>
                    <div className="grid grid-cols-3 gap-3">
                        {(Object.keys(themes) as ThemeName[]).map((name) => {
                            const locked = !plan.themes.includes(name);
                            return (
                                <button
                                    key={name}
                                    type="button"
                                    disabled={locked}
                                    onClick={() => setData('theme', name)}
                                    className={cn(
                                        'relative h-20 rounded-lg border-2 text-sm font-medium transition',
                                        themes[name].page,
                                        data.theme === name ? 'border-primary ring-2 ring-primary/30' : 'border-transparent',
                                        locked && 'cursor-not-allowed opacity-50',
                                    )}
                                >
                                    {themes[name].label}
                                    {locked && (
                                        <span className="absolute top-1 right-1 flex items-center gap-0.5 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">
                                            <Lock className="size-3" /> Pro
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                    <InputError message={errors.theme} />
                </section>

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="is_published"
                        checked={data.is_published}
                        onCheckedChange={(checked) => setData('is_published', checked === true)}
                    />
                    <Label htmlFor="is_published">Yayında (adresi bilen herkes görebilir)</Label>
                </div>

                <Button disabled={processing}>{card ? 'Değişiklikleri kaydet' : 'Kartı oluştur'}</Button>
            </form>

            {/* Canlı önizleme: form verisi değiştikçe anında güncellenir */}
            <aside className="hidden lg:block">
                <div className="sticky top-4">
                    <p className="mb-2 text-center text-xs text-muted-foreground">Canlı önizleme</p>
                    <div className="overflow-hidden rounded-[2rem] border-8 border-neutral-800 shadow-xl">
                        <CardPreview card={{ ...data, avatar_url: avatarPreview }} className="min-h-[600px]" />
                    </div>
                </div>
            </aside>
        </div>
    );
}
