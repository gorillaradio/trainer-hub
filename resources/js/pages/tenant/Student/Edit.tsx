import { PageHeader } from '@/components/page-header';
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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StudentForm } from '@/components/student-form';
import TenantLayout from '@/layouts/tenant-layout';
import type { Student, StudentStatus } from '@/types';
import { useTenant } from '@/hooks/use-tenant';
import { Head, router } from '@inertiajs/react';
import { Archive, Pause } from 'lucide-react';
import type { ReactElement } from 'react';

type Props = {
    student: Student;
    statuses: StudentStatus[];
};

export default function StudentsEdit({ student, statuses }: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    return (
        <>
            <Head title={`Modifica ${student.first_name} ${student.last_name}`} />
            <div className="mx-auto w-full max-w-2xl p-4">
                <PageHeader
                    sticky
                    title={
                        <h1 className="text-2xl font-semibold">Modifica allievo</h1>
                    }
                    actions={
                        <Button type="submit" form="student-form">
                            Salva modifiche
                        </Button>
                    }
                />
                <StudentForm formId="student-form" student={student} statuses={statuses} submitLabel="Salva modifiche" />

                {student.status === 'active' && (
                    <Card className="mt-8 border-destructive">
                        <CardHeader>
                            <CardTitle className="text-destructive">Zona pericolosa</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="font-medium">Sospendi allievo</p>
                                    <p className="text-sm text-muted-foreground">
                                        L'allievo non potrà partecipare alle attività fino alla riattivazione.
                                    </p>
                                </div>
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="outline">
                                            <Pause data-icon="inline-start" />
                                            Sospendi
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Sospendere questo allievo?</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                {student.first_name} {student.last_name} verrà sospeso.
                                                Potrai riattivarlo in qualsiasi momento.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Annulla</AlertDialogCancel>
                                            <AlertDialogAction
                                                onClick={() => router.put(`${prefix}/students/${student.id}/suspend`)}
                                            >
                                                Sospendi
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="font-medium">Archivia allievo</p>
                                    <p className="text-sm text-muted-foreground">
                                        L'allievo verrà archiviato e rimosso dalla lista attivi.
                                    </p>
                                </div>
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="destructive">
                                            <Archive data-icon="inline-start" />
                                            Archivia
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Archiviare questo allievo?</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                {student.first_name} {student.last_name} verrà archiviato.
                                                Potrai recuperarlo in futuro se necessario.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Annulla</AlertDialogCancel>
                                            <AlertDialogAction
                                                onClick={() => router.put(`${prefix}/students/${student.id}/archive`)}
                                            >
                                                Archivia
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

StudentsEdit.layout = (page: ReactElement<Props>) => {
    const { student } = page.props;
    return (
        <TenantLayout breadcrumbs={[
            { title: 'Allievi', href: 'students' },
            { title: `${student.last_name} ${student.first_name}`, href: `students/${student.id}` },
            { title: 'Modifica', href: '#' },
        ]}>
            {page}
        </TenantLayout>
    );
};
