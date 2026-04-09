import type { Auth, SharedTenant } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            tenant: SharedTenant | null;
            [key: string]: unknown;
        };
    }
}
