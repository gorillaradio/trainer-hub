export type Group = {
    id: string;
    name: string;
    description: string | null;
    color: string;
    monthly_fee_amount: number; // cents
    students_count?: number;
    students?: Array<{ id: string; first_name: string; last_name: string }>;
    created_at: string;
    updated_at: string;
};
