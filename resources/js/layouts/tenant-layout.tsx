import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { TenantBottomNav } from '@/components/tenant-bottom-nav';
import { TenantMobileHeader } from '@/components/tenant-mobile-header';
import { TenantSidebar } from '@/components/tenant-sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import { useTenant } from '@/hooks/use-tenant';
import type { AppLayoutProps } from '@/types';

export default function TenantLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    const isMobile = useIsMobile();
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    const resolvedBreadcrumbs = breadcrumbs.map((item) => ({
        ...item,
        href: typeof item.href === 'string' && !item.href.startsWith('/') && item.href !== '#'
            ? `${prefix}/${item.href}`
            : item.href,
    }));

    if (isMobile) {
        return (
            <div className="flex min-h-svh flex-col bg-background">
                <TenantMobileHeader />
                <main className="flex flex-1 flex-col pb-16">{children}</main>
                <TenantBottomNav />
            </div>
        );
    }

    return (
        <AppShell variant="sidebar">
            <TenantSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={resolvedBreadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
