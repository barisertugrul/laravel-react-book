import { Link, router, usePage } from '@inertiajs/react';
import { LayoutDashboard, LogOut, Package, Settings, ShoppingBag } from 'lucide-react';
import type { ReactNode } from 'react';
import CartController from '@/actions/App/Http/Controllers/CartController';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { home, login, logout, register } from '@/routes';
import { index as adminOrders } from '@/routes/admin/orders';
import { edit as profile } from '@/routes/profile';
import type { User } from '@/types';

/** Müşterinin gördüğü mağaza düzeni: üst menü, sepet rozeti, alt bilgi */
export default function ShopLayout({ children }: { children: ReactNode }) {
    const { name, auth, cartCount } = usePage().props;
    // Vitrin herkese açık: giriş yapılmamışsa user null gelir
    const user = auth.user as User | null;

    return (
        <div className="flex min-h-screen flex-col bg-stone-50 text-stone-900 dark:bg-neutral-950 dark:text-neutral-100">
            <header className="border-b bg-white dark:bg-neutral-900">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
                    <Link href={home()} className="flex items-center gap-2 font-semibold">
                        <span className="flex size-8 items-center justify-center rounded-md bg-stone-900 text-white dark:bg-white dark:text-black">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        {name}
                    </Link>

                    <nav className="flex items-center gap-2">
                        <Button asChild variant="ghost" className="relative">
                            <Link href={CartController.index()} aria-label="Sepet">
                                <ShoppingBag />
                                <span className="hidden sm:inline">Sepet</span>
                                {cartCount > 0 && (
                                    <span className="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-amber-600 text-xs text-white">
                                        {cartCount}
                                    </span>
                                )}
                            </Link>
                        </Button>

                        {user ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">{user.name.split(' ')[0]}</Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-56">
                                    <DropdownMenuLabel className="font-normal text-muted-foreground">{user.email}</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link href={OrderController.index()}>
                                            <Package /> Siparişlerim
                                        </Link>
                                    </DropdownMenuItem>
                                    {user.role === 'admin' && (
                                        <DropdownMenuItem asChild>
                                            <Link href={adminOrders()}>
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
                {name} · Taze kavrulmuş kahve · 750 ₺ üzeri kargo bedava
            </footer>
        </div>
    );
}
