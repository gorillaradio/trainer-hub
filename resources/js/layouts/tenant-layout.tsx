import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { TenantBottomNav } from '@/components/tenant-bottom-nav';
import { TenantMobileHeader } from '@/components/tenant-mobile-header';
import { TenantSidebar } from '@/components/tenant-sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import type { AppLayoutProps } from '@/types';

export default function TenantLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    const isMobile = useIsMobile();

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
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
