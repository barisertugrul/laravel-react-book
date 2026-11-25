import { Head, Link } from '@inertiajs/react';
import { ListChecks, Play } from 'lucide-react';
import OperationController from '@/actions/App/Http/Controllers/OperationController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import type { OperationListItem } from '@/types';

export default function OperationIndex({ operations }: { operations: OperationListItem[] }) {
    return (
        <>
            <Head title="İşlemler" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading title="İşlemler" description="Birden çok sitede çalışan kurma ve güncelleme işleri." />
                    <Button asChild>
                        <Link href={OperationController.create()}>
                            <Play /> Toplu işlem
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">İşlem</th>
                                <th className="p-3 font-medium">Durum</th>
                                <th className="hidden p-3 font-medium md:table-cell">Başlatan</th>
                                <th className="p-3 font-medium">Tarih</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {operations.map((op) => (
                                <tr key={op.id} className="hover:bg-muted/30">
                                    <td className="p-3">
                                        <Link href={OperationController.show(op.id)} className="font-medium hover:underline">
                                            {op.label}
                                        </Link>
                                    </td>
                                    <td className="p-3 whitespace-nowrap tabular-nums">
                                        {op.finished_at === null ? (
                                            <Badge variant="secondary">
                                                {op.finished}/{op.total} sürüyor
                                            </Badge>
                                        ) : (
                                            <span className={op.failed > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400'}>
                                                {op.total - op.failed}/{op.total} başarılı
                                            </span>
                                        )}
                                    </td>
                                    <td className="hidden p-3 text-muted-foreground md:table-cell">{op.user ?? '—'}</td>
                                    <td className="p-3 whitespace-nowrap text-muted-foreground">{formatDateTime(op.created_at)}</td>
                                </tr>
                            ))}
                            {operations.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="p-10 text-center text-muted-foreground">
                                        <ListChecks className="mx-auto mb-2 size-5" />
                                        Henüz işlem yok.
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

OperationIndex.layout = { breadcrumbs: [{ title: 'İşlemler', href: OperationController.index() }] };
