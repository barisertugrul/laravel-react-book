import { Link } from '@inertiajs/react';
import { CreditCard, FolderGit2, LayoutGrid, ListChecks, Package, Plus, Users } from 'lucide-react';
import AgencyController from '@/actions/App/Http/Controllers/AgencyController';
import BillingController from '@/actions/App/Http/Controllers/Billing/BillingController';
import OperationController from '@/actions/App/Http/Controllers/OperationController';
import PackageController from '@/actions/App/Http/Controllers/PackageController';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import { AgencySwitcher } from '@/components/agency-switcher';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Siteler', href: dashboard(), icon: LayoutGrid },
    { title: 'Site ekle', href: SiteController.create(), icon: Plus },
    { title: 'İşlemler', href: OperationController.index(), icon: ListChecks },
    { title: 'Paketler', href: PackageController.index(), icon: Package },
    { title: 'Ajans ve ekip', href: AgencyController.edit(), icon: Users },
    { title: 'Abonelik', href: BillingController.index(), icon: CreditCard },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Kitabın deposu',
        href: 'https://github.com/barisertugrul/laravel-react-book',
        icon: FolderGit2,
    },
];

export function AppSidebar() {
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
                <AgencySwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
