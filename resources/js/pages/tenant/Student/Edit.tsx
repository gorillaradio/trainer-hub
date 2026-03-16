import { StudentForm } from '@/components/student-form';
import TenantLayout from '@/layouts/tenant-layout';
import type { Student, StudentStatus } from '@/types';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    student: Student;
    statuses: StudentStatus[];
};

export default function StudentsEdit({ student, statuses }: Props) {
    return (
        <>
            <Head title={`Modifica ${student.first_name} ${student.last_name}`} />
            <div className="mx-auto w-full max-w-2xl p-4">
                <h1 className="mb-6 text-2xl font-semibold">
                    Modifica allievo
                </h1>
                <StudentForm student={student} statuses={statuses} submitLabel="Salva modifiche" />
            </div>
        </>
    );
}

StudentsEdit.layout = (page: ReactNode) => {
    const { student } = page.props as unknown as Props;
    return (
        <TenantLayout breadcrumbs={[
            { title: 'Allievi', href: '../../students' },
            { title: `${student.last_name} ${student.first_name}`, href: `../../students/${student.id}` },
            { title: 'Modifica', href: '#' },
        ]}>
            {page}
        </TenantLayout>
    );
};
