import { Form, Head } from '@inertiajs/react';
import { Check } from 'lucide-react';
import PlanController from '@/actions/App/Http/Controllers/PlanController';
import { themes } from '@/components/card-preview';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PlanInfo } from '@/types';

const prices: Record<PlanInfo['value'], string> = { free: '0 ₺', pro: '149 ₺ / ay' };

export default function PlanPage({
    plans,
    current,
    cardCount,
}: {
    plans: PlanInfo[];
    current: PlanInfo['value'];
    cardCount: number;
}) {
    return (
        <>
            <Head title="Planım" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Planım"
                    description={`Şu anda ${cardCount} kartınız var. Bu bölümde ödeme alınmıyor; plan değişikliği anında uygulanır.`}
                />
                <div className="grid max-w-3xl gap-4 md:grid-cols-2">
                    {plans.map((plan) => (
                        <Card key={plan.value} className={plan.value === current ? 'border-primary' : ''}>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>{plan.label}</CardTitle>
                                    {plan.value === current && <Badge>Mevcut plan</Badge>}
                                </div>
                                <p className="text-2xl font-semibold">{prices[plan.value]}</p>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <ul className="space-y-2 text-sm">
                                    <li className="flex gap-2"><Check className="size-4 text-green-600" /> {plan.card_limit} kart</li>
                                    <li className="flex gap-2">
                                        <Check className="size-4 text-green-600" />
                                        {plan.themes.length} tema ({plan.themes.map((t) => themes[t].label).join(', ')})
                                    </li>
                                    <li className="flex gap-2"><Check className="size-4 text-green-600" /> vCard ve QR kod</li>
                                </ul>
                                {plan.value !== current && (
                                    // Her plan kendi küçük formuyla gönderilir; seçilen plan gizli alanda
                                    <Form {...PlanController.update.form()} options={{ preserveScroll: true }}>
                                        {({ processing, errors }) => (
                                            <>
                                                <input type="hidden" name="plan" value={plan.value} />
                                                <Button disabled={processing} className="w-full">
                                                    {plan.value === 'pro' ? "Pro'ya geç" : 'Ücretsiz plana dön'}
                                                </Button>
                                                <InputError message={errors.plan} className="mt-2" />
                                            </>
                                        )}
                                    </Form>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

PlanPage.layout = {
    breadcrumbs: [{ title: 'Planım', href: PlanController.show() }],
};
