import { Link } from '@inertiajs/react';
import { BookOpen, CalendarDays, Clock, ExternalLink, FolderGit2, Scissors } from 'lucide-react';
import CalendarController from '@/actions/App/Http/Controllers/Admin/CalendarController';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import WorkingHoursController from '@/actions/App/Http/Controllers/Admin/WorkingHoursController';
import BookingController from '@/actions/App/Http/Controllers/BookingController';
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
    { title: 'Takvim', href: CalendarController(), icon: CalendarDays },
    { title: 'Hizmetler', href: ServiceController.index(), icon: Scissors },
    { title: 'Çalışma saatleri', href: WorkingHoursController.edit(), icon: Clock },
];

const footerNavItems: NavItem[] = [
    { title: 'Randevu sayfası', href: BookingController.show().url, icon: ExternalLink },
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
                            <Link href={CalendarController()} prefetch>
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
