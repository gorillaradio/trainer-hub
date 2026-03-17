import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import TenantLayout from '@/layouts/tenant-layout';
import { statusLabel, statusVariant } from '@/lib/student-status';
import type { Student } from '@/types';
import { useTenant } from '@/hooks/use-tenant';
import { Head, Link } from '@inertiajs/react';
import { format, parse } from 'date-fns';
import { it } from 'date-fns/locale';
import { ArrowLeft, Pencil } from 'lucide-react';
import type { ReactElement } from 'react';

function formatDate(value: string | null): string | null {
    if (!value) return null;
    return format(parse(value, 'yyyy-MM-dd', new Date()), 'dd/MM/yyyy', { locale: it });
}

type Props = {
    student: Student;
};

function Field({ label, value }: { label: string; value: string | null }) {
    if (!value) return null;
    return (
        <div>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1">{value}</p>
        </div>
    );
}

export default function StudentsShow({ student }: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    return (
        <>
            <Head title={`${student.first_name} ${student.last_name}`} />
            <div className="mx-auto w-full max-w-2xl p-4">
                <PageHeader
                    sticky
                    title={
                        <h1 className="text-2xl font-semibold">
                            {student.last_name} {student.first_name}
                        </h1>
                    }
                    actions={
                        <>
                            <Button variant="outline" onClick={() => window.history.back()}>
                                <ArrowLeft data-icon="inline-start" />
                                Indietro
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`${prefix}/students/${student.id}/edit`}>
                                    <Pencil data-icon="inline-start" />
                                    Modifica
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>Dati personali</CardTitle>
                                <Badge variant={statusVariant[student.status]}>
                                    {statusLabel[student.status]}
                                </Badge>
                            </div>
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

                    {student.address && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Indirizzo</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Field label="Indirizzo" value={student.address} />
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Contatti di emergenza</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {student.emergency_contacts?.length > 0 ? (
                                <div className="flex flex-col gap-4">
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
                            <Field label="Data iscrizione" value={formatDate(student.enrolled_at)} />
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

StudentsShow.layout = (page: ReactElement<Props>) => {
    const { student } = page.props;
    return (
        <TenantLayout breadcrumbs={[
            { title: 'Allievi', href: 'students' },
            { title: `${student.last_name} ${student.first_name}`, href: '#' },
        ]}>
            {page}
        </TenantLayout>
    );
};
