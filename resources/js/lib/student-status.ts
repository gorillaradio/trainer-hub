export const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'outline',
    active: 'default',
    suspended: 'destructive',
};

export const statusLabel: Record<string, string> = {
    pending: 'In attesa',
    active: 'Attivo',
    suspended: 'Sospeso',
};
