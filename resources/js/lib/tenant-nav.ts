import { CreditCard, FileText, LayoutGrid, Users } from 'lucide-react';
import type { NavItem } from '@/types';

export function getTenantNavItems(tenantSlug: string): NavItem[] {
    const prefix = `/app/${tenantSlug}`;

    return [
        { title: 'Dashboard', href: `${prefix}/dashboard`, icon: LayoutGrid },
        { title: 'Allievi', href: `${prefix}/students`, icon: Users },
        { title: 'Pagamenti', href: `${prefix}/payments`, icon: CreditCard },
        { title: 'Documenti', href: `${prefix}/documents`, icon: FileText },
    ];
}
