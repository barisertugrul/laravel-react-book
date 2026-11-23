import { Form, Head, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';

type Props = {
    invitation: { agency: string; inviter: string | null; email: string; role: string; expires_at: string };
    pending: boolean;
    emailMatches: boolean;
    acceptUrl: string;
};

export default function InvitationShow({ invitation, pending, emailMatches, acceptUrl }: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Ajans daveti" />
            <div className="mx-auto max-w-lg space-y-6 p-4 pt-10">
                <div className="flex items-center gap-3">
                    <Building2 className="size-8 text-muted-foreground" />
                    <Heading title={invitation.agency} description={`${invitation.inviter ?? 'Ajans sahibi'} sizi ${invitation.role} olarak davet ediyor.`} />
                </div>

                {!pending && <p className="rounded-xl border p-4 text-sm">Bu davetin süresi dolmuş ya da daha önce kullanılmış. Ajans sahibinden yeni bir davet isteyin.</p>}

                {pending && !emailMatches && (
                    <p className="rounded-xl border border-amber-300 p-4 text-sm">
                        Davet <strong>{invitation.email}</strong> adresine gönderildi; siz <strong>{auth.user.email}</strong> ile giriş yaptınız. Doğru hesapla giriş yapın.
                    </p>
                )}

                {pending && emailMatches && (
                    // Wayfinder yerine sunucunun ürettiği imzalı adres: imza sorgu dizgisinde taşınır
                    <Form action={acceptUrl} method="post" className="space-y-3">
                        {({ processing }) => (
                            <>
                                <p className="text-sm text-muted-foreground">Davet {formatDate(invitation.expires_at)} tarihine kadar geçerli.</p>
                                <Button disabled={processing} className="w-full">
                                    Daveti kabul et
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
