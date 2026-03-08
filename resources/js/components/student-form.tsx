import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Student, StudentStatus } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

type StudentFormData = {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    date_of_birth: string;
    fiscal_code: string;
    address: string;
    emergency_contact_name: string;
    emergency_contact_phone: string;
    notes: string;
    status: string;
    enrolled_at: string;
};

type Props = {
    student?: Student;
    statuses: StudentStatus[];
    submitLabel: string;
};

export function StudentForm({ student, statuses, submitLabel }: Props) {
    const { tenant } = usePage().props as { tenant: { slug: string } };

    const { data, setData, post, put, processing, errors } = useForm<StudentFormData>({
        first_name: student?.first_name ?? '',
        last_name: student?.last_name ?? '',
        email: student?.email ?? '',
        phone: student?.phone ?? '',
        date_of_birth: student?.date_of_birth ?? '',
        fiscal_code: student?.fiscal_code ?? '',
        address: student?.address ?? '',
        emergency_contact_name: student?.emergency_contact_name ?? '',
        emergency_contact_phone: student?.emergency_contact_phone ?? '',
        notes: student?.notes ?? '',
        status: student?.status ?? 'active',
        enrolled_at: student?.enrolled_at ?? new Date().toISOString().split('T')[0],
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (student) {
            put(`/app/${tenant.slug}/students/${student.id}`);
        } else {
            post(`/app/${tenant.slug}/students`);
        }
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Dati personali</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="first_name">Nome *</Label>
                        <Input
                            id="first_name"
                            value={data.first_name}
                            onChange={(e) => setData('first_name', e.target.value)}
                        />
                        {errors.first_name && <p className="text-sm text-destructive">{errors.first_name}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="last_name">Cognome *</Label>
                        <Input
                            id="last_name"
                            value={data.last_name}
                            onChange={(e) => setData('last_name', e.target.value)}
                        />
                        {errors.last_name && <p className="text-sm text-destructive">{errors.last_name}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="phone">Telefono</Label>
                        <Input
                            id="phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                        {errors.phone && <p className="text-sm text-destructive">{errors.phone}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="date_of_birth">Data di nascita</Label>
                        <Input
                            id="date_of_birth"
                            type="date"
                            value={data.date_of_birth}
                            onChange={(e) => setData('date_of_birth', e.target.value)}
                        />
                        {errors.date_of_birth && <p className="text-sm text-destructive">{errors.date_of_birth}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="fiscal_code">Codice fiscale</Label>
                        <Input
                            id="fiscal_code"
                            value={data.fiscal_code}
                            onChange={(e) => setData('fiscal_code', e.target.value.toUpperCase())}
                            maxLength={16}
                        />
                        {errors.fiscal_code && <p className="text-sm text-destructive">{errors.fiscal_code}</p>}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Indirizzo</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-2">
                        <Label htmlFor="address">Indirizzo</Label>
                        <Input
                            id="address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                        />
                        {errors.address && <p className="text-sm text-destructive">{errors.address}</p>}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Contatto di emergenza</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="emergency_contact_name">Nome contatto</Label>
                        <Input
                            id="emergency_contact_name"
                            value={data.emergency_contact_name}
                            onChange={(e) => setData('emergency_contact_name', e.target.value)}
                        />
                        {errors.emergency_contact_name && <p className="text-sm text-destructive">{errors.emergency_contact_name}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="emergency_contact_phone">Telefono contatto</Label>
                        <Input
                            id="emergency_contact_phone"
                            value={data.emergency_contact_phone}
                            onChange={(e) => setData('emergency_contact_phone', e.target.value)}
                        />
                        {errors.emergency_contact_phone && <p className="text-sm text-destructive">{errors.emergency_contact_phone}</p>}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Iscrizione</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="status">Stato</Label>
                        <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.status && <p className="text-sm text-destructive">{errors.status}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="enrolled_at">Data iscrizione</Label>
                        <Input
                            id="enrolled_at"
                            type="date"
                            value={data.enrolled_at}
                            onChange={(e) => setData('enrolled_at', e.target.value)}
                        />
                        {errors.enrolled_at && <p className="text-sm text-destructive">{errors.enrolled_at}</p>}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Note</CardTitle>
                </CardHeader>
                <CardContent>
                    <Textarea
                        id="notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={4}
                        placeholder="Note aggiuntive sull'allievo..."
                    />
                    {errors.notes && <p className="text-sm text-destructive">{errors.notes}</p>}
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
