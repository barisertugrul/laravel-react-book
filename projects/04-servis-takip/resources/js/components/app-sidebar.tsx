import { Link, usePage } from '@inertiajs/react';
import { BookOpen, ClipboardList, ExternalLink, FolderGit2, Plus, Users } from 'lucide-react';
import TeamController from '@/actions/App/Http/Controllers/TeamController';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import TrackingController from '@/actions/App/Http/Controllers/TrackingController';
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Servis kayıtları', href: TicketController.index(), icon: ClipboardList },
    { title: 'Yeni kayıt', href: TicketController.create(), icon: Plus },
];

// Yalnızca yöneticilere gösterilen menü öğeleri
const adminNavItems: NavItem[] = [{ title: 'Ekip', href: TeamController.index(), icon: Users }];

const footerNavItems: NavItem[] = [
    { title: 'Müşteri takip sayfası', href: TrackingController.lookup().url, icon: ExternalLink },
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
    const { auth } = usePage().props;
    const items = auth.user.role === 'admin' ? [...mainNavItems, ...adminNavItems] : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={TicketController.index()} prefetch>
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
