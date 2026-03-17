import { PageHeader } from '@/components/page-header';
import { useTenant } from '@/hooks/use-tenant';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpDown, Plus } from 'lucide-react';
import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import TenantLayout from '@/layouts/tenant-layout';
import { statusLabel, statusVariant } from '@/lib/student-status';
import type { Student, StudentFilters, StudentStatus } from '@/types';

function SortableHeader({ field, children, onSort }: { field: string; children: React.ReactNode; onSort: (field: string) => void }) {
    return (
        <TableHead>
            <button
                className="flex items-center gap-1 hover:text-foreground"
                onClick={() => onSort(field)}
            >
                {children}
                <ArrowUpDown className="size-4" />
            </button>
        </TableHead>
    );
}

type Props = {
    students: Student[];
    filters: StudentFilters;
    statuses: StudentStatus[];
};

export default function StudentsIndex({ students, filters, statuses }: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    function handleSearch(value: string) {
        router.get(`${prefix}/students`, { ...filters, search: value }, {
            preserveState: true,
            replace: true,
        });
    }

    function handleStatusFilter(value: string) {
        router.get(`${prefix}/students`, { ...filters, status: value === 'all' ? '' : value }, {
            preserveState: true,
            replace: true,
        });
    }

    function handleSort(field: string) {
        const direction = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get(`${prefix}/students`, { ...filters, sort: field, direction }, {
            preserveState: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Allievi" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <PageHeader
                    sticky
                    title={<h1 className="text-2xl font-semibold">Allievi</h1>}
                    actions={
                        <Button asChild>
                            <Link href={`${prefix}/students/create`}>
                                <Plus data-icon="inline-start" />
                                Nuovo allievo
                            </Link>
                        </Button>
                    }
                >
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            placeholder="Cerca per nome, cognome o email..."
                            defaultValue={filters.search}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="sm:max-w-sm"
                        />
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={handleStatusFilter}
                        >
                            <SelectTrigger className="sm:w-48">
                                <SelectValue placeholder="Tutti gli stati" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Tutti gli stati</SelectItem>
                                    {statuses.map((s) => (
                                        <SelectItem key={s.value} value={s.value}>
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </PageHeader>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHeader field="last_name" onSort={handleSort}>Cognome</SortableHeader>
                                <SortableHeader field="first_name" onSort={handleSort}>Nome</SortableHeader>
                                <TableHead className="hidden md:table-cell">Email</TableHead>
                                <TableHead className="hidden sm:table-cell">Telefono</TableHead>
                                <SortableHeader field="status" onSort={handleSort}>Stato</SortableHeader>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {students.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                        Nessun allievo trovato.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                students.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell>
                                            <Link
                                                href={`${prefix}/students/${student.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {student.last_name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{student.first_name}</TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {student.email ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            {student.effective_phone ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={statusVariant[student.status]}>
                                                {statusLabel[student.status]}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

            </div>
        </>
    );
}

StudentsIndex.layout = (page: ReactNode) => <TenantLayout breadcrumbs={[{ title: 'Allievi', href: '#' }]}>{page}</TenantLayout>;
