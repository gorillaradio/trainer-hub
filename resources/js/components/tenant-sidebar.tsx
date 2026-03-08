import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Users, CreditCard, FileText } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
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

export function TenantSidebar() {
    const { tenant } = usePage().props as { tenant: { id: string; name: string; slug: string } };
    const prefix = `/app/${tenant.slug}`;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: `${prefix}/dashboard`,
            icon: LayoutGrid,
        },
        {
            title: 'Allievi',
            href: `${prefix}/students`,
            icon: Users,
        },
        {
            title: 'Pagamenti',
            href: `${prefix}/payments`,
            icon: CreditCard,
        },
        {
            title: 'Documenti',
            href: `${prefix}/documents`,
            icon: FileText,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={`${prefix}/dashboard`} prefetch>
                                <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                                </div>
                                <div className="ml-1 grid flex-1 text-left text-sm">
                                    <span className="mb-0.5 truncate leading-tight font-semibold">
                                        {tenant.name}
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
