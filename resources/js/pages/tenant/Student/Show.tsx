import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import TenantLayout from '@/layouts/tenant-layout';
import { statusLabel, statusVariant } from '@/lib/student-status';
import type { Student } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { format, parse } from 'date-fns';
import { it } from 'date-fns/locale';
import { Pencil, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';

function formatDate(value: string | null): string | null {
    if (!value) return null;
    return format(parse(value, 'yyyy-MM-dd', new Date()), 'dd/MM/yyyy', { locale: it });
}

type Props = {
    student: Student;
};

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1">{value || '—'}</p>
        </div>
    );
}

export default function StudentsShow({ student }: Props) {
    const { tenant } = usePage().props as { tenant: { slug: string } };
    const prefix = `/app/${tenant.slug}`;

    function handleDelete() {
        router.delete(`${prefix}/students/${student.id}`);
    }

    return (
        <>
            <Head title={`${student.first_name} ${student.last_name}`} />
            <div className="mx-auto w-full max-w-2xl p-4">
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold">
                            {student.last_name} {student.first_name}
                        </h1>
                        <Badge variant={statusVariant[student.status]}>
                            {statusLabel[student.status]}
                        </Badge>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`${prefix}/students/${student.id}/edit`}>
                                <Pencil className="mr-2 size-4" />
                                Modifica
                            </Link>
                        </Button>
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="destructive">
                                    <Trash2 className="mr-2 size-4" />
                                    Archivia
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Archiviare questo allievo?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        L'allievo {student.first_name} {student.last_name} verrà archiviato.
                                        Potrai recuperarlo in futuro se necessario.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Annulla</AlertDialogCancel>
                                    <AlertDialogAction onClick={handleDelete}>
                                        Archivia
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Dati personali</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Nome" value={student.first_name} />
                                <Field label="Cognome" value={student.last_name} />
                                <Field label="Email" value={student.email} />
                                <Field
                                    label="Telefono"
                                    value={
                                        student.effective_phone
                                            ? student.phone_contact_id
                                                ? `${student.effective_phone} (da contatto: ${student.emergency_contacts?.find(c => c.id === student.phone_contact_id)?.name ?? '—'})`
                                                : student.effective_phone
                                            : null
                                    }
                                />
                                <Field label="Data di nascita" value={formatDate(student.date_of_birth)} />
                                <Field label="Codice fiscale" value={student.fiscal_code} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Indirizzo</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Field label="Indirizzo" value={student.address} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Contatti di emergenza</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {student.emergency_contacts?.length > 0 ? (
                                <div className="space-y-4">
                                    {student.emergency_contacts.map((contact) => (
                                        <div key={contact.id} className="grid gap-4 sm:grid-cols-2">
                                            <Field label="Nome contatto" value={contact.name} />
                                            <Field label="Telefono contatto" value={contact.phone} />
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">Nessun contatto di emergenza</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Iscrizione</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Data iscrizione" value={formatDate(student.enrolled_at)} />
                                <Field label="Stato" value={statusLabel[student.status]} />
                            </div>
                        </CardContent>
                    </Card>

                    {student.notes && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Note</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="whitespace-pre-wrap">{student.notes}</p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

StudentsShow.layout = (page: ReactNode) => {
    const { student } = page.props as unknown as Props;
    return (
        <TenantLayout breadcrumbs={[
            { title: 'Allievi', href: '../students' },
            { title: `${student.last_name} ${student.first_name}`, href: '#' },
        ]}>
            {page}
        </TenantLayout>
    );
};
