export type PaymentMonthlyFee = {
    id: string;
    period: string;
    expected_amount: number;
    due_date: string;
};

export type PaymentEnrollmentFee = {
    id: string;
    expected_amount: number;
    starts_at: string;
    expires_at: string;
};

export type Payment = {
    id: string;
    amount: number;
    payment_method: string;
    paid_at: string;
    notes: string | null;
    monthly_fees: PaymentMonthlyFee[];
    enrollment_fees: PaymentEnrollmentFee[];
};

export type LatestEnrollment = {
    id: string;
    starts_at: string;
    expires_at: string;
    expected_amount: number;
} | null;

export type PaymentData = {
    effectiveRate: number | null;
    balance: number;
    uncoveredPeriods: string[];
    latestEnrollment: LatestEnrollment;
    enrollmentExpired: boolean;
    payments: Payment[];
};
