import { Form, Head, usePage } from '@inertiajs/react';
import { Package as PackageIcon, Trash2, Upload } from 'lucide-react';
import PackageController from '@/actions/App/Http/Controllers/PackageController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import type { PackageItem } from '@/types';

export default function PackageIndex({ packages }: { packages: PackageItem[] }) {
    const { auth } = usePage().props;
    const isOwner = auth.agency?.role === 'owner';

    return (
        <>
            <Head title="Paketler" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Paket kütüphanesi"
                    description="wordpress.org’da olmayan eklentiler (ör. ücretli eklentiler, Ajans Asistanı’nın yeni sürümü). Siteler paketi süreli imzalı bir adresten indirir ve SHA-256 özetini doğrular."
                />

                {isOwner && (
                    <Form {...PackageController.store.form()} resetOnSuccess className="flex flex-wrap items-start gap-3 rounded-xl border p-4">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1">
                                    <Input type="file" name="file" accept=".zip" required className="max-w-sm" />
                                    <InputError message={errors.file} />
                                </div>
                                <Button disabled={processing} data-test="upload-package-button">
                                    {processing ? <Spinner /> : <Upload />} Zip yükle
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Eklenti</th>
                                <th className="p-3 font-medium">Sürüm</th>
                                <th className="hidden p-3 font-medium md:table-cell">SHA-256</th>
                                <th className="hidden p-3 text-right font-medium md:table-cell">Boyut</th>
                                <th className="p-3 font-medium">Yüklendi</th>
                                {isOwner && <th className="p-3" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {packages.map((p) => (
                                <tr key={p.id}>
                                    <td className="p-3">
                                        <div className="font-medium">{p.name}</div>
                                        <div className="font-mono text-xs text-muted-foreground">{p.slug}</div>
                                    </td>
                                    <td className="p-3 tabular-nums">{p.version}</td>
                                    <td className="hidden p-3 font-mono text-xs text-muted-foreground md:table-cell">{p.sha256?.slice(0, 16)}…</td>
                                    <td className="hidden p-3 text-right tabular-nums md:table-cell">{((p.size ?? 0) / 1024).toFixed(0)} KB</td>
                                    <td className="p-3 whitespace-nowrap text-muted-foreground">{p.created_at ? formatDate(p.created_at) : '—'}</td>
                                    {isOwner && (
                                        <td className="p-3 text-right">
                                            <Form {...PackageController.destroy.form(p.id)} options={{ preserveScroll: true }}>
                                                {({ processing }) => (
                                                    <Button variant="ghost" size="icon" disabled={processing} aria-label={`${p.name} ${p.version} sil`}>
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </Form>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {packages.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="p-10 text-center text-muted-foreground">
                                        <PackageIcon className="mx-auto mb-2 size-5" />
                                        Kütüphane boş.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PackageIndex.layout = { breadcrumbs: [{ title: 'Paketler', href: PackageController.index() }] };
