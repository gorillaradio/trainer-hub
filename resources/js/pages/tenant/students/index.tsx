import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
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
import type { PaginatedData, Student, StudentFilters, StudentStatus } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowUpDown, Plus } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    students: PaginatedData<Student>;
    filters: StudentFilters;
    statuses: StudentStatus[];
};

export default function StudentsIndex({ students, filters, statuses }: Props) {
    const { tenant } = usePage().props as { tenant: { slug: string } };
    const prefix = `/app/${tenant.slug}`;

    function handleSearch(value: string) {
        router.get(`${prefix}/students`, { ...filters, search: value, page: 1 }, {
            preserveState: true,
            replace: true,
        });
    }

    function handleStatusFilter(value: string) {
        router.get(`${prefix}/students`, { ...filters, status: value === 'all' ? '' : value, page: 1 }, {
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

    function SortableHeader({ field, children }: { field: string; children: React.ReactNode }) {
        return (
            <TableHead>
                <button
                    className="flex items-center gap-1 hover:text-foreground"
                    onClick={() => handleSort(field)}
                >
                    {children}
                    <ArrowUpDown className="size-4" />
                </button>
            </TableHead>
        );
    }

    return (
        <>
            <Head title="Allievi" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Allievi</h1>
                    <Button asChild>
                        <Link href={`${prefix}/students/create`}>
                            <Plus className="mr-2 size-4" />
                            Nuovo allievo
                        </Link>
                    </Button>
                </div>

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
                            <SelectItem value="all">Tutti gli stati</SelectItem>
                            {statuses.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHeader field="last_name">Cognome</SortableHeader>
                                <SortableHeader field="first_name">Nome</SortableHeader>
                                <TableHead className="hidden md:table-cell">Email</TableHead>
                                <TableHead className="hidden sm:table-cell">Telefono</TableHead>
                                <SortableHeader field="status">Stato</SortableHeader>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {students.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                        Nessun allievo trovato.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                students.data.map((student) => (
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
                                            {student.phone ?? '—'}
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

                {students.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            {students.from}–{students.to} di {students.total} allievi
                        </p>
                        <div className="flex gap-2">
                            {students.links.map((link, i) => (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    asChild={!!link.url}
                                >
                                    {link.url ? (
                                        <Link
                                            href={link.url}
                                            preserveState
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    )}
                                </Button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

StudentsIndex.layout = (page: ReactNode) => <TenantLayout breadcrumbs={[{ title: 'Allievi', href: '#' }]}>{page}</TenantLayout>;
