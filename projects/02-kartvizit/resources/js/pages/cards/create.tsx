import { Head } from '@inertiajs/react';
import CardController from '@/actions/App/Http/Controllers/CardController';
import CardForm from '@/components/card-form';
import Heading from '@/components/heading';
import type { PlanInfo } from '@/types';

export default function CardsCreate({ plan }: { plan: PlanInfo }) {
    return (
        <>
            <Head title="Yeni kart" />
            <div className="p-4">
                <Heading title="Yeni kart" description="Bilgilerinizi girin, sağda kartınızın nasıl görüneceğini izleyin." />
                <CardForm plan={plan} />
            </div>
        </>
    );
}

CardsCreate.layout = {
    breadcrumbs: [
        { title: 'Kartlarım', href: CardController.index() },
        { title: 'Yeni kart', href: CardController.create() },
    ],
};
