import { Head, Link } from '@inertiajs/react';
import { FolderTree, QrCode, UtensilsCrossed } from 'lucide-react';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import QrCodeController from '@/actions/App/Http/Controllers/QrCodeController';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type Stats = {
    categories: number;
    products: number;
    unavailable: number;
};

export default function Dashboard({ stats }: { stats: Stats }) {
    const cards = [
        {
            title: 'Kategoriler',
            value: stats.categories,
            icon: FolderTree,
            href: CategoryController.index(),
        },
        {
            title: 'Ürünler',
            value: stats.products,
            icon: UtensilsCrossed,
            href: ProductController.index(),
            note: `${stats.unavailable} ürün şu an menüde gizli`,
        },
    ];

    return (
        <>
            <Head title="Panel" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Hoş geldiniz"
                    description="Menünüzü buradan yönetin, QR kodunuzu masalara yerleştirin."
                />
                <div className="grid gap-4 md:grid-cols-3">
                    {cards.map((card) => (
                        <Link key={card.title} href={card.href}>
                            <Card className="transition-colors hover:bg-accent">
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="text-sm font-medium">
                                        {card.title}
                                    </CardTitle>
                                    <card.icon className="size-5 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <p className="text-3xl font-semibold">
                                        {card.value}
                                    </p>
                                    {card.note && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {card.note}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                    <Link href={QrCodeController.show()}>
                        <Card className="h-full transition-colors hover:bg-accent">
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-sm font-medium">
                                    QR Kod
                                </CardTitle>
                                <QrCode className="size-5 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Menünüzün QR kodunu görüntüleyin ve indirin.
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Panel', href: dashboard() }],
};
