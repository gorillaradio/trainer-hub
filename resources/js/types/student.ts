export type Student = {
    id: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    date_of_birth: string | null;
    fiscal_code: string | null;
    address: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    status: 'active' | 'inactive' | 'suspended';
    enrolled_at: string | null;
    created_at: string;
    updated_at: string;
};

export type StudentStatus = {
    value: string;
    label: string;
};

export type StudentFilters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    first_page_url: string | null;
    last_page_url: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};
