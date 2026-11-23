import { Head, useForm } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import OperationController from '@/actions/App/Http/Controllers/OperationController';
import HealthBadge from '@/components/health-badge';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { hostOf } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { OperationSite, OperationType, PackageItem } from '@/types';

type Props = { sites: OperationSite[]; packages: PackageItem[]; preselected: number[] };

type FormData = {
    type: OperationType;
    source: 'wporg' | 'package';
    slug: string;
    package_id: number | '';
    activate: boolean;
    file: string;
    license_key: string;
    site_ids: number[];
};

const typeLabels: Record<OperationType, { title: string; description: string }> = {
    install: { title: 'Eklenti kur', description: 'wordpress.org ya da paket' },
    update: { title: 'Eklenti güncelle', description: 'wordpress.org’daki yeni sürüme' },
    license: { title: 'Lisans', description: 'Ajans Asistanı lisansını etkinleştir' },
};

/** Her türde ekranda kendi hata satırı olan alanlar */
const visibleFields: Record<OperationType, string[]> = {
    install: ['slug', 'package_id', 'site_ids'],
    update: ['file', 'site_ids'],
    license: ['license_key', 'site_ids'],
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function OperationCreate({ sites, packages, preselected }: Props) {
    const form = useForm<FormData>({
        type: 'install',
        source: 'wporg',
        slug: '',
        package_id: packages[0]?.id ?? '',
        activate: true,
        file: '',
        license_key: '',
        site_ids: preselected,
    });
    const { data, setData, errors, processing } = form;

    // Güncellenebilecek eklentiler: en az bir sitenin son raporunda güncellemesi görünenler
    const updatable = useMemo(() => {
        const map = new Map<string, { file: string; name: string; sites: number[] }>();
        for (const site of sites) {
            for (const plugin of site.plugins.filter((p) => p.update !== null)) {
                const entry = map.get(plugin.file) ?? { file: plugin.file, name: plugin.name, sites: [] };
                entry.sites.push(site.id);
                map.set(plugin.file, entry);
            }
        }

        return [...map.values()].sort((a, b) => b.sites.length - a.sites.length);
    }, [sites]);

    const chooseUpdate = (file: string) => {
        // Eklenti seçilince güncellemesi olan siteler kendiliğinden işaretlenir
        setData((current) => ({ ...current, file, site_ids: updatable.find((u) => u.file === file)?.sites ?? [] }));
    };

    const toggleSite = (id: number, checked: boolean) => {
        setData('site_ids', checked ? [...data.site_ids, id] : data.site_ids.filter((s) => s !== id));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(OperationController.store());
    };

    const allSelected = sites.length > 0 && data.site_ids.length === sites.length;

    return (
        <>
            <Head title="Toplu işlem" />
            <form onSubmit={submit} className="grid gap-6 p-4 lg:grid-cols-5">
                <div className="space-y-6 lg:col-span-2">
                    <Heading title="Toplu işlem" description="Bir eklentiyi seçtiğiniz bütün sitelerde kurun ya da güncelleyin." />

                    <div className="grid grid-cols-3 gap-2" role="radiogroup">
                        {(['install', 'update', 'license'] as const).map((type) => (
                            <button
                                key={type}
                                type="button"
                                role="radio"
                                aria-checked={data.type === type}
                                onClick={() => setData('type', type)}
                                className={cn('rounded-lg border p-3 text-left text-sm', data.type === type && 'border-primary ring-2 ring-primary/20')}
                            >
                                <div className="font-medium">{typeLabels[type].title}</div>
                                <div className="text-muted-foreground">{typeLabels[type].description}</div>
                            </button>
                        ))}
                    </div>

                    {data.type === 'install' ? (
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="source">Kaynak</Label>
                                <select id="source" className={selectClass} value={data.source} onChange={(e) => setData('source', e.target.value as FormData['source'])}>
                                    <option value="wporg">wordpress.org eklenti dizini</option>
                                    <option value="package" disabled={packages.length === 0}>
                                        Paket kütüphanesi{packages.length === 0 ? ' (boş)' : ''}
                                    </option>
                                </select>
                            </div>

                            {data.source === 'wporg' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="slug">Eklentinin kısa adı</Label>
                                    <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value.trim().toLowerCase())} placeholder="contact-form-7" />
                                    <p className="text-xs text-muted-foreground">wordpress.org/plugins/<strong>contact-form-7</strong>/ adresindeki son parça.</p>
                                    <InputError message={errors.slug} />
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="package_id">Paket</Label>
                                    <select id="package_id" className={selectClass} value={data.package_id} onChange={(e) => setData('package_id', Number(e.target.value))}>
                                        {packages.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name} {p.version}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.package_id} />
                                </div>
                            )}

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={data.activate} onCheckedChange={(checked) => setData('activate', checked === true)} />
                                Kurduktan sonra etkinleştir
                            </label>
                        </div>
                    ) : data.type === 'license' ? (
                        <div className="grid gap-2">
                            <Label htmlFor="license_key">Ajans Asistanı lisans anahtarı</Label>
                            <textarea
                                id="license_key"
                                rows={4}
                                spellCheck={false}
                                value={data.license_key}
                                onChange={(e) => setData('license_key', e.target.value.trim())}
                                placeholder="ANH1.eyJsaWQi…"
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs break-all shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            />
                            <p className="text-xs text-muted-foreground">
                                Her site lisans sunucusuna kendisi bağlanır ve bir yer harcar. Lisansın site sınırı dolunca kalan siteler “sınır doldu” hatası alır.
                            </p>
                            <InputError message={errors.license_key} />
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor="file">Eklenti</Label>
                            <select id="file" className={selectClass} value={data.file} onChange={(e) => chooseUpdate(e.target.value)}>
                                <option value="">Seçin…</option>
                                {updatable.map((u) => (
                                    <option key={u.file} value={u.file}>
                                        {u.name} ({u.sites.length} sitede güncelleme var)
                                    </option>
                                ))}
                            </select>
                            {updatable.length === 0 && <p className="text-sm text-muted-foreground">Son kontrollere göre güncellemesi bekleyen eklenti yok.</p>}
                            <InputError message={errors.file} />
                        </div>
                    )}

                    <Button disabled={processing || data.site_ids.length === 0} data-test="start-operation-button">
                        {processing ? <Spinner /> : <Play />} {data.site_ids.length} sitede başlat
                    </Button>

                    {/* Alanı şu an ekranda olmayan bir hata (ör. gizli bir alan) sessizce kaybolmasın */}
                    {Object.entries(errors)
                        .filter(([field]) => !visibleFields[data.type].includes(field))
                        .map(([field, message]) => (
                            <InputError key={field} message={message} />
                        ))}
                </div>

                <div className="space-y-2 lg:col-span-3">
                    <div className="flex items-center justify-between">
                        <h2 className="font-medium">Siteler</h2>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={allSelected} onCheckedChange={(checked) => setData('site_ids', checked === true ? sites.map((s) => s.id) : [])} />
                            Tümü
                        </label>
                    </div>
                    <InputError message={errors.site_ids} />
                    <ul className="divide-y rounded-xl border">
                        {sites.map((site) => {
                            const plugin = data.type === 'update' && data.file ? site.plugins.find((p) => p.file === data.file) : undefined;

                            return (
                                <li key={site.id}>
                                    <label className="flex cursor-pointer items-center gap-3 p-3 hover:bg-muted/30">
                                        <Checkbox checked={data.site_ids.includes(site.id)} onCheckedChange={(checked) => toggleSite(site.id, checked === true)} />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">{site.name}</span>
                                            <span className="block truncate text-xs text-muted-foreground">{hostOf(site.url)}</span>
                                        </span>
                                        {data.type === 'update' && data.file && (
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {plugin ? (plugin.update ? `${plugin.version} → ${plugin.update}` : `${plugin.version} (güncel)`) : 'kurulu değil'}
                                            </span>
                                        )}
                                        <HealthBadge status={site.health_status} />
                                    </label>
                                </li>
                            );
                        })}
                        {sites.length === 0 && <li className="p-6 text-center text-sm text-muted-foreground">Henüz site yok.</li>}
                    </ul>
                </div>
            </form>
        </>
    );
}

OperationCreate.layout = {
    breadcrumbs: [
        { title: 'İşlemler', href: OperationController.index() },
        { title: 'Toplu işlem', href: OperationController.create() },
    ],
};
