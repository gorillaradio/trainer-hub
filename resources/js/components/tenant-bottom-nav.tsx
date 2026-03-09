import { Link, usePage } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { getTenantNavItems } from '@/lib/tenant-nav';

export function TenantBottomNav() {
    const { tenant } = usePage().props as { tenant: { id: string; name: string; slug: string } };
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const items = getTenantNavItems(tenant.slug);

    return (
        <nav className="fixed inset-x-0 bottom-0 z-50 border-t bg-background pb-[env(safe-area-inset-bottom)]">
            <div className="flex h-16 items-center justify-around">
                {items.map((item) => {
                    const active = isCurrentOrParentUrl(item.href);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'flex flex-1 flex-col items-center justify-center gap-1 py-2 text-xs transition-colors',
                                active
                                    ? 'text-primary'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {item.icon && <item.icon className="size-5" />}
                            <span>{item.title}</span>
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
