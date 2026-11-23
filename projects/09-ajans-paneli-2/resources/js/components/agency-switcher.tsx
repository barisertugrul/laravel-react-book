import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import AgencyController from '@/actions/App/Http/Controllers/AgencyController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';

/** Kenar çubuğunda: etkin ajans ve üyesi olunan diğer ajanslara geçiş */
export function AgencySwitcher() {
    const { auth } = usePage().props;

    if (!auth.agency) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton className="border" data-test="agency-switcher">
                            <Building2 />
                            <span className="truncate font-medium">{auth.agency.name}</span>
                            <ChevronsUpDown className="ml-auto" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-(--radix-dropdown-menu-trigger-width) min-w-56" align="start">
                        <DropdownMenuLabel className="text-xs text-muted-foreground">Ajanslarım</DropdownMenuLabel>
                        {auth.agencies.map((agency) => (
                            <DropdownMenuItem
                                key={agency.id}
                                onSelect={() => agency.id !== auth.agency?.id && router.post(AgencyController.select.url(agency.id))}
                            >
                                <span className="truncate">{agency.name}</span>
                                {agency.id === auth.agency?.id && <Check className="ml-auto" />}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href={AgencyController.create()}>
                                <Plus /> Yeni ajans
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
