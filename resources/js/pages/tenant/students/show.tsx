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
import type { Student } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    student: Student;
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
    active: 'default',
    inactive: 'secondary',
    suspended: 'destructive',
};

const statusLabel: Record<string, string> = {
    active: 'Attivo',
    inactive: 'Inattivo',
    suspended: 'Sospeso',
};

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="mt-1">{value || '—'}</dd>
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
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Nome" value={student.first_name} />
                                <Field label="Cognome" value={student.last_name} />
                                <Field label="Email" value={student.email} />
                                <Field label="Telefono" value={student.phone} />
                                <Field label="Data di nascita" value={student.date_of_birth} />
                                <Field label="Codice fiscale" value={student.fiscal_code} />
                            </dl>
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
                            <CardTitle>Contatto di emergenza</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Nome contatto" value={student.emergency_contact_name} />
                                <Field label="Telefono contatto" value={student.emergency_contact_phone} />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Iscrizione</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Data iscrizione" value={student.enrolled_at} />
                                <Field label="Stato" value={statusLabel[student.status]} />
                            </dl>
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
