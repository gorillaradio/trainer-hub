import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/hooks/use-tenant';
import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useEffect } from 'react';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    studentId: string;
    effectiveRate: number | null;
    balance: number;
    enrollmentExpired: boolean;
    uncoveredCount: number;
};

type FormData = {
    months: number;
    amount: string;
    notes: string;
};

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

function centsToEuros(cents: number): string {
    return (cents / 100).toFixed(2);
}

function eurosToCents(euros: string): number {
    return Math.round(parseFloat(euros) * 100);
}

function computeSuggestedAmount(months: number, effectiveRate: number | null, balance: number): string {
    if (effectiveRate === null) return '';
    const raw = effectiveRate * months - balance;
    const clamped = Math.max(0, raw);
    return centsToEuros(clamped);
}

export default function RegisterMonthlyDialog({
    open,
    onOpenChange,
    studentId,
    effectiveRate,
    balance,
    enrollmentExpired,
    uncoveredCount,
}: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    const { data, setData, post, transform, processing, errors, reset } = useForm<FormData>({
        months: 1,
        amount: computeSuggestedAmount(1, effectiveRate, balance),
        notes: '',
    });

    transform((values) => ({
        months: values.months,
        amount: eurosToCents(values.amount),
        notes: values.notes,
    }));

    useEffect(() => {
        if (open) {
            reset();
            setData({
                months: 1,
                amount: computeSuggestedAmount(1, effectiveRate, balance),
                notes: '',
            });
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function handleMonthsChange(value: number) {
        const clamped = Math.min(12, Math.max(1, value));
        setData('months', clamped);
        setData('amount', computeSuggestedAmount(clamped, effectiveRate, balance));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(`${prefix}/students/${studentId}/payments/monthly`, {
            onSuccess: () => onOpenChange(false),
            preserveScroll: true,
        });
    }

    const debtCents = -balance;
    const hasDebt = debtCents > 0;
    const hasCredit = balance > 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registra mensilità</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    {enrollmentExpired && (
                        <Alert variant="destructive">
                            <AlertTriangle />
                            <AlertDescription>
                                L'iscrizione è scaduta. Rinnova l'iscrizione prima di registrare mensilità.
                            </AlertDescription>
                        </Alert>
                    )}

                    {hasDebt && (
                        <Alert>
                            <AlertTriangle />
                            <AlertDescription>
                                Debito pregresso: {formatCurrency(debtCents)}. L'importo suggerito include il saldo.
                            </AlertDescription>
                        </Alert>
                    )}

                    {hasCredit && (
                        <Alert>
                            <AlertTriangle />
                            <AlertDescription>
                                Credito disponibile: {formatCurrency(balance)}. L'importo suggerito tiene conto del credito.
                            </AlertDescription>
                        </Alert>
                    )}

                    <Field>
                        <FieldLabel htmlFor="months">Numero di mesi (1–12)</FieldLabel>
                        <Input
                            id="months"
                            type="number"
                            min={1}
                            max={12}
                            value={data.months}
                            onChange={(e) => handleMonthsChange(parseInt(e.target.value, 10) || 1)}
                            disabled={processing}
                        />
                        {errors.months && <FieldError>{errors.months}</FieldError>}
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="amount">
                            Importo (€)
                            {effectiveRate !== null && (
                                <span className="ml-1 text-xs font-normal text-muted-foreground">
                                    — tariffa: {formatCurrency(effectiveRate)}/mese
                                </span>
                            )}
                        </FieldLabel>
                        <Input
                            id="amount"
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            disabled={processing}
                        />
                        {errors.amount && <FieldError>{errors.amount}</FieldError>}
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="notes">Note (opzionale)</FieldLabel>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                            disabled={processing}
                        />
                        {errors.notes && <FieldError>{errors.notes}</FieldError>}
                    </Field>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Annulla
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Registra
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
