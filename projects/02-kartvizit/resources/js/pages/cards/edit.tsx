import { Head } from '@inertiajs/react';
import CardController from '@/actions/App/Http/Controllers/CardController';
import CardForm from '@/components/card-form';
import Heading from '@/components/heading';
import type { Card, PlanInfo } from '@/types';

export default function CardsEdit({ card, plan }: { card: Card; plan: PlanInfo }) {
    return (
        <>
            <Head title={`${card.full_name} düzenle`} />
            <div className="p-4">
                <Heading title="Kartı düzenle" description={card.public_url} />
                <CardForm card={card} plan={plan} />
            </div>
        </>
    );
}

CardsEdit.layout = {
    breadcrumbs: [
        { title: 'Kartlarım', href: CardController.index() },
        { title: 'Düzenle', href: CardController.index() },
    ],
};
