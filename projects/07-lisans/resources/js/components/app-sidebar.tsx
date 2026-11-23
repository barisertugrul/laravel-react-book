import { Link, usePage } from '@inertiajs/react';
import { FolderGit2, KeyRound, Store } from 'lucide-react';
import AdminLicenseController from '@/actions/App/Http/Controllers/Admin/LicenseController';
import LicenseController from '@/actions/App/Http/Controllers/LicenseController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import type { NavItem } from '@/types';

// Yönetici menüsü
const adminNavItems: NavItem[] = [{ title: 'Lisanslar', href: AdminLicenseController.index(), icon: KeyRound }];

// Müşteri, ayarlar sayfasında bu düzeni görür: yönetim bağlantıları yok
const customerNavItems: NavItem[] = [{ title: 'Lisanslarım', href: LicenseController.index(), icon: KeyRound }];

const footerNavItems: NavItem[] = [
    { title: 'Mağazaya git', href: home(), icon: Store },
    {
        title: 'Kitabın deposu',
        href: 'https://github.com/barisertugrul/laravel-react-book',
        icon: FolderGit2,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const items = auth.user.role === 'admin' ? adminNavItems : customerNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
