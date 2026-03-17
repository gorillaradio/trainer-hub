import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

type Props = {
    title: ReactNode;
    actions?: ReactNode;
    children?: ReactNode;
    sticky?: boolean;
};

export function PageHeader({ title, actions, children, sticky = false }: Props) {
    return (
        <div
            className={cn(
                '-mx-4 mb-6 border-b bg-background px-4 py-3',
                sticky && 'sticky top-0 z-10',
            )}
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">{title}</div>
                {actions && <div className="flex w-full justify-between gap-2 sm:w-auto sm:justify-end">{actions}</div>}
            </div>
            {children && <div className="mt-3">{children}</div>}
        </div>
    );
}
