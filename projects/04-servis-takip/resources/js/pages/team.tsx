import { Form, Head, router, usePage } from '@inertiajs/react';
import TeamController from '@/actions/App/Http/Controllers/TeamController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Option } from '@/types';

type Member = { id: number; name: string; email: string; role: string; open_tickets_count: number };

export default function Team({ users, roles }: { users: Member[]; roles: Option[] }) {
    const { errors } = usePage().props;

    return (
        <>
            <Head title="Ekip" />
            <div className="max-w-3xl space-y-4 p-4">
                <Heading title="Ekip" description="Bu sayfayı yalnızca yöneticiler görebilir." />
                <InputError message={errors.role} />

                {/* Kayıt sayfası kapalı: yeni kullanıcıları yönetici buradan ekler */}
                <Form {...TeamController.store.form()} resetOnSuccess className="flex flex-wrap items-start gap-2 rounded-xl border p-3">
                    {({ processing, errors: formErrors }) => (
                        <>
                            <div className="min-w-40 flex-1">
                                <Input name="name" placeholder="Ad soyad" aria-label="Ad soyad" />
                                <InputError message={formErrors.name} />
                            </div>
                            <div className="min-w-48 flex-1">
                                <Input name="email" type="email" placeholder="E-posta" aria-label="E-posta" />
                                <InputError message={formErrors.email} />
                            </div>
                            <select name="role" defaultValue="technician" aria-label="Rol" className="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                            </select>
                            <Button disabled={processing}>Ekle</Button>
                        </>
                    )}
                </Form>

                <ul className="divide-y rounded-xl border">
                    {users.map((user) => (
                        <li key={user.id} className="flex items-center gap-4 p-3">
                            <div className="flex-1">
                                <p className="font-medium">{user.name}</p>
                                <p className="text-sm text-muted-foreground">{user.email}</p>
                            </div>
                            <Badge variant="secondary">{user.open_tickets_count} açık kayıt</Badge>
                            <select
                                aria-label={`${user.name} rolü`}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                value={user.role}
                                onChange={(e) => router.patch(TeamController.update.url(user.id), { role: e.target.value }, { preserveScroll: true })}
                            >
                                {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                            </select>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

Team.layout = {
    breadcrumbs: [{ title: 'Ekip', href: TeamController.index() }],
};
