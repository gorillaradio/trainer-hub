import type { SharedTenant } from '@/types/auth';
import { usePage } from '@inertiajs/react';

export function useTenant(): SharedTenant {
    const { tenant } = usePage().props;

    if (!tenant) {
        throw new Error('useTenant() must be used inside a tenant route.');
    }

    return tenant;
}
