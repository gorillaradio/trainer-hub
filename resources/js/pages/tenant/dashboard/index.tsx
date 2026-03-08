import { Head, usePage } from '@inertiajs/react';
import TenantLayout from '@/layouts/tenant-layout';
import type { ReactNode } from 'react';

export default function TenantDashboard() {
    const { tenant } = usePage().props as { tenant: { name: string } };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-4">
                <h1 className="text-2xl font-semibold">{tenant.name}</h1>
                <p className="text-muted-foreground">Dashboard in costruzione</p>
            </div>
        </>
    );
}

TenantDashboard.layout = (page: ReactNode) => <TenantLayout>{page}</TenantLayout>;
