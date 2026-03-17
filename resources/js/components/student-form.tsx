import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Student, StudentStatus } from '@/types';
import { useTenant } from '@/hooks/use-tenant';
import { useForm } from '@inertiajs/react';
import { Phone, Plus, Trash2, Users } from 'lucide-react';
import type { FormEvent } from 'react';

type EmergencyContactFormData = {
    name: string;
    phone: string;
};

type StudentFormData = {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    date_of_birth: string;
    fiscal_code: string;
    address: string;
    emergency_contacts: EmergencyContactFormData[];
    phone_contact_index: number | null;
    notes: string;
    status: string;
    enrolled_at: string;
};

type Props = {
    student?: Student;
    statuses: StudentStatus[];
    submitLabel: string;
    formId?: string;
};

function initPhoneContactIndex(student?: Student): number | null {
    if (!student?.phone_contact_id || !student.emergency_contacts) return null;
    const idx = student.emergency_contacts.findIndex(c => c.id === student.phone_contact_id);
    return idx >= 0 ? idx : null;
}

export function StudentForm({ student, statuses, submitLabel, formId }: Props) {
    const tenant = useTenant();

    const { data, setData, post, put, processing, errors, transform } = useForm<StudentFormData>({
        first_name: student?.first_name ?? '',
        last_name: student?.last_name ?? '',
        email: student?.email ?? '',
        phone: student?.phone ?? '',
        date_of_birth: student?.date_of_birth ?? '',
        fiscal_code: student?.fiscal_code ?? '',
        address: student?.address ?? '',
        emergency_contacts: student?.emergency_contacts?.map(c => ({ name: c.name, phone: c.phone })) ?? [],
        phone_contact_index: initPhoneContactIndex(student),
        notes: student?.notes ?? '',
        status: student?.status ?? 'active',
        enrolled_at: student?.enrolled_at ?? new Date().toISOString().split('T')[0],
    });

    transform((formData) => ({
        ...formData,
        emergency_contacts: formData.emergency_contacts.filter(c => c.name.trim() || c.phone.trim()),
    }));

    function addContact() {
        setData('emergency_contacts', [...data.emergency_contacts, { name: '', phone: '' }]);
    }

    function removeContact(index: number) {
        const updated = data.emergency_contacts.filter((_, i) => i !== index);
        setData(prev => ({
            ...prev,
            emergency_contacts: updated,
            phone_contact_index:
                prev.phone_contact_index === index
                    ? null
                    : prev.phone_contact_index !== null && prev.phone_contact_index > index
                        ? prev.phone_contact_index - 1
                        : prev.phone_contact_index,
            phone: prev.phone_contact_index === index ? '' : prev.phone,
        }));
    }

    function updateContact(index: number, field: 'name' | 'phone', value: string) {
        const updated = [...data.emergency_contacts];
        updated[index] = { ...updated[index], [field]: value };
        setData('emergency_contacts', updated);
    }

    function selectPhoneContact(index: number | null) {
        setData(prev => ({
            ...prev,
            phone_contact_index: index,
            phone: index === null ? '' : '',
        }));
    }

    const linkedContact = data.phone_contact_index !== null
        ? data.emergency_contacts[data.phone_contact_index]
        : null;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (student) {
            put(`/app/${tenant.slug}/students/${student.id}`);
        } else {
            post(`/app/${tenant.slug}/students`);
        }
    }

    return (
        <form id={formId} onSubmit={handleSubmit} className="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Dati personali</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="first_name">Nome *</Label>
                        <Input
                            id="first_name"
                            value={data.first_name}
                            onChange={(e) => setData('first_name', e.target.value)}
                        />
                        {errors.first_name && <p className="text-sm text-destructive">{errors.first_name}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="last_name">Cognome *</Label>
                        <Input
                            id="last_name"
                            value={data.last_name}
                            onChange={(e) => setData('last_name', e.target.value)}
                        />
                        {errors.last_name && <p className="text-sm text-destructive">{errors.last_name}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="phone">Telefono</Label>
                        <div className="flex gap-2">
                            {linkedContact ? (
                                <div className="flex flex-1 items-center gap-2 rounded-md border bg-muted px-3 py-2 text-sm">
                                    <Phone className="size-4 shrink-0 text-muted-foreground" />
                                    <span className="truncate">
                                        {linkedContact.phone || '—'}{' '}
                                        <span className="text-muted-foreground">
                                            (da {linkedContact.name || 'contatto'})
                                        </span>
                                    </span>
                                </div>
                            ) : (
                                <Input
                                    id="phone"
                                    className="flex-1"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                />
                            )}
                            {data.emergency_contacts.length > 0 && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button type="button" variant="outline" size="icon">
                                            <Users className="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem onClick={() => selectPhoneContact(null)}>
                                            <Phone data-icon="inline-start" />
                                            Numero proprio
                                        </DropdownMenuItem>
                                        {data.emergency_contacts.map((contact, i) => (
                                            <DropdownMenuItem key={i} onClick={() => selectPhoneContact(i)}>
                                                <Users data-icon="inline-start" />
                                                {contact.name || `Contatto ${i + 1}`} — {contact.phone || '—'}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>
                        {errors.phone && <p className="text-sm text-destructive">{errors.phone}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="date_of_birth">Data di nascita</Label>
                        <DatePicker
                            id="date_of_birth"
                            value={data.date_of_birth}
                            onChange={(value) => setData('date_of_birth', value)}
                            placeholder="Seleziona data di nascita"
                        />
                        {errors.date_of_birth && <p className="text-sm text-destructive">{errors.date_of_birth}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
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
                    <div className="flex flex-col gap-2">
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
                    <div className="flex items-center justify-between">
                        <CardTitle>Contatti di emergenza</CardTitle>
                        <Button type="button" variant="outline" size="sm" onClick={addContact}>
                            <Plus data-icon="inline-start" />
                            Aggiungi contatto
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    {data.emergency_contacts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Nessun contatto di emergenza aggiunto.</p>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {data.emergency_contacts.map((contact, i) => (
                                <div key={i} className="flex items-start gap-2">
                                    <div className="grid flex-1 gap-4 sm:grid-cols-2">
                                        <div className="flex flex-col gap-2">
                                            <Label>Nome contatto *</Label>
                                            <Input
                                                value={contact.name}
                                                onChange={(e) => updateContact(i, 'name', e.target.value)}
                                                placeholder="Nome e cognome"
                                            />
                                            {errors[`emergency_contacts.${i}.name` as keyof typeof errors] && (
                                                <p className="text-sm text-destructive">
                                                    {errors[`emergency_contacts.${i}.name` as keyof typeof errors]}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <Label>Telefono contatto *</Label>
                                            <Input
                                                value={contact.phone}
                                                onChange={(e) => updateContact(i, 'phone', e.target.value)}
                                                placeholder="Numero di telefono"
                                            />
                                            {errors[`emergency_contacts.${i}.phone` as keyof typeof errors] && (
                                                <p className="text-sm text-destructive">
                                                    {errors[`emergency_contacts.${i}.phone` as keyof typeof errors]}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="mt-8 shrink-0"
                                        onClick={() => removeContact(i)}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                    {errors.emergency_contacts && (
                        <p className="mt-2 text-sm text-destructive">{errors.emergency_contacts}</p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Iscrizione</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
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

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="enrolled_at">Data iscrizione</Label>
                        <DatePicker
                            id="enrolled_at"
                            value={data.enrolled_at}
                            onChange={(value) => setData('enrolled_at', value)}
                            placeholder="Seleziona data iscrizione"
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

            {!formId && (
                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </div>
            )}
        </form>
    );
}
