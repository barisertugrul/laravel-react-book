import { Link, router, usePage } from '@inertiajs/react';
import { KeyRound, LayoutDashboard, LogOut, Settings } from 'lucide-react';
import type { ReactNode } from 'react';
import LicenseController from '@/actions/App/Http/Controllers/LicenseController';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { home, login, logout, register } from '@/routes';
import { index as adminLicenses } from '@/routes/admin/licenses';
import { edit as profile } from '@/routes/profile';
import type { User } from '@/types';

/** Müşterinin gördüğü mağaza düzeni: üst menü ve alt bilgi (Bölüm 6'dan uyarlandı) */
export default function ShopLayout({ children }: { children: ReactNode }) {
    const { name, auth } = usePage().props;
    // Vitrin herkese açık: giriş yapılmamışsa user null gelir
    const user = auth.user as User | null;

    return (
        <div className="flex min-h-screen flex-col bg-slate-50 text-slate-900 dark:bg-neutral-950 dark:text-neutral-100">
            <header className="border-b bg-white dark:bg-neutral-900">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
                    <Link href={home()} className="flex items-center gap-2 font-semibold">
                        <span className="flex size-8 items-center justify-center rounded-md bg-indigo-600 text-white">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        {name}
                    </Link>

                    <nav className="flex items-center gap-2">
                        {user ? (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={LicenseController.index()}>
                                        <KeyRound />
                                        <span className="hidden sm:inline">Lisanslarım</span>
                                    </Link>
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline">{user.name.split(' ')[0]}</Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-56">
                                        <DropdownMenuLabel className="font-normal text-muted-foreground">{user.email}</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        {user.role === 'admin' && (
                                            <DropdownMenuItem asChild>
                                                <Link href={adminLicenses()}>
                                                    <LayoutDashboard /> Yönetim paneli
                                                </Link>
                                            </DropdownMenuItem>
                                        )}
                                        <DropdownMenuItem asChild>
                                            <Link href={profile()}>
                                                <Settings /> Ayarlar
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link href={logout()} as="button" className="w-full" onClick={() => router.flushAll()}>
                                                <LogOut /> Çıkış yap
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={login()}>Giriş</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>Kayıt ol</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">{children}</main>

            <footer className="border-t py-6 text-center text-sm text-muted-foreground">
                {name} · WordPress eklentileri · Ömür boyu lisans, 1 yıl güncelleme
            </footer>
        </div>
    );
}
