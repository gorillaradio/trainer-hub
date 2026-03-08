import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Users, CreditCard, FileText } from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

type NavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
};

export function TenantBottomNav() {
    const { tenant } = usePage().props as { tenant: { id: string; name: string; slug: string } };
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const prefix = `/app/${tenant.slug}`;

    const items: NavItem[] = [
        { title: 'Dashboard', href: `${prefix}/dashboard`, icon: LayoutGrid },
        { title: 'Allievi', href: `${prefix}/students`, icon: Users },
        { title: 'Pagamenti', href: `${prefix}/payments`, icon: CreditCard },
        { title: 'Documenti', href: `${prefix}/documents`, icon: FileText },
    ];

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
                            <item.icon className="size-5" />
                            <span>{item.title}</span>
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
