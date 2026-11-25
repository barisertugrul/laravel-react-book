import { Form, Head, usePage } from '@inertiajs/react';
import { MailPlus, UserMinus, X } from 'lucide-react';
import AgencyController from '@/actions/App/Http/Controllers/AgencyController';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';
import MemberController from '@/actions/App/Http/Controllers/MemberController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import type { AgencyRef, Invitation, Member, RoleOption } from '@/types';

type Props = { agency: AgencyRef; canManage: boolean; members: Member[]; invitations: Invitation[]; roles: RoleOption[] };

export default function AgencyEdit({ agency, canManage, members, invitations, roles }: Props) {
    const { auth } = usePage().props;
    const roleLabel = (value: string) => roles.find((r) => r.value === value)?.label ?? value;

    return (
        <>
            <Head title="Ajans ve ekip" />
            <div className="max-w-4xl space-y-8 p-4">
                <Heading title="Ajans ve ekip" description="Sahipler ekibi ve ajans ayarlarını yönetir, siteleri kaldırabilir. Üyeler site ekler ve izler." />

                {canManage && (
                    <Form {...AgencyController.update.form()} options={{ preserveScroll: true }} className="flex flex-wrap items-end gap-2">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Ajans adı</Label>
                                    <Input id="name" name="name" defaultValue={agency.name} required maxLength={80} className="w-72" />
                                    <InputError message={errors.name} />
                                </div>
                                <Button variant="outline" disabled={processing}>
                                    Kaydet
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <section className="space-y-2">
                    <h2 className="font-medium">Ekip ({members.length})</h2>
                    <ul className="divide-y rounded-xl border text-sm">
                        {members.map((member) => (
                            <li key={member.id} className="flex flex-wrap items-center gap-3 p-3">
                                <div className="min-w-48 flex-1">
                                    <div className="font-medium">
                                        {member.name}
                                        {member.id === auth.user.id && <span className="font-normal text-muted-foreground"> (siz)</span>}
                                    </div>
                                    <div className="text-muted-foreground">{member.email}</div>
                                </div>
                                <Badge variant={member.role === 'owner' ? 'default' : 'secondary'}>{roleLabel(member.role)}</Badge>
                                <span className="w-32 text-muted-foreground">{formatDate(member.joined_at)}</span>
                                {canManage && member.id !== auth.user.id && (
                                    <Form {...MemberController.destroy.form(member.id)} options={{ preserveScroll: true }}>
                                        {({ processing }) => (
                                            <Button variant="ghost" size="sm" disabled={processing} aria-label={`${member.name} ekipten çıkar`}>
                                                <UserMinus /> Çıkar
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                    <ErrorLine name="member" />
                </section>

                {canManage && (
                    <section className="space-y-3">
                        <h2 className="font-medium">Davet et</h2>
                        <Form {...InvitationController.store.form()} resetOnSuccess options={{ preserveScroll: true }} className="flex flex-wrap items-start gap-2 rounded-xl border p-3">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1">
                                        <Input name="email" type="email" required placeholder="e-posta@ornek.com" aria-label="E-posta" className="w-72" />
                                        <InputError message={errors.email} />
                                    </div>
                                    <select name="role" defaultValue="member" aria-label="Rol" className="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                        {roles.map((r) => (
                                            <option key={r.value} value={r.value}>
                                                {r.label}
                                            </option>
                                        ))}
                                    </select>
                                    <Button disabled={processing}>
                                        <MailPlus /> Davet gönder
                                    </Button>
                                </>
                            )}
                        </Form>

                        {invitations.length > 0 && (
                            <ul className="divide-y rounded-xl border text-sm">
                                {invitations.map((invitation) => (
                                    <li key={invitation.id} className="flex flex-wrap items-center gap-3 p-3">
                                        <span className="min-w-48 flex-1 font-medium">{invitation.email}</span>
                                        <Badge variant="outline">{roleLabel(invitation.role)}</Badge>
                                        <span className="text-muted-foreground">
                                            {invitation.inviter ?? '—'} · {formatDate(invitation.expires_at)} tarihine kadar
                                        </span>
                                        <Form {...InvitationController.destroy.form(invitation.id)} options={{ preserveScroll: true }}>
                                            {({ processing }) => (
                                                <Button variant="ghost" size="sm" disabled={processing} aria-label={`${invitation.email} davetini geri al`}>
                                                    <X /> Geri al
                                                </Button>
                                            )}
                                        </Form>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

function ErrorLine({ name }: { name: string }) {
    const { errors } = usePage().props as { errors: Record<string, string> };

    return <InputError message={errors[name]} />;
}

AgencyEdit.layout = { breadcrumbs: [{ title: 'Ajans ve ekip', href: AgencyController.edit() }] };
