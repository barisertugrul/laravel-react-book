import { Link } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    FileText,
    FolderGit2,
    LayoutGrid,
    Users,
} from 'lucide-react';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
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
import { dashboard, reports } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Özet', href: dashboard(), icon: LayoutGrid },
    { title: 'Faturalar', href: InvoiceController.index(), icon: FileText },
    { title: 'Cariler', href: ContactController.index(), icon: Users },
    { title: 'Raporlar', href: reports(), icon: BarChart3 },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Kitabın deposu',
        href: 'https://github.com/barisertugrul/laravel-react-book',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
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
