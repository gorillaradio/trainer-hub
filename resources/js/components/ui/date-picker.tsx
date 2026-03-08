import { format, parse } from 'date-fns';
import { it } from 'date-fns/locale';
import { Calendar as CalendarIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { useState } from 'react';

type DatePickerProps = {
    id?: string;
    value: string; // YYYY-MM-DD string or empty
    onChange: (value: string) => void;
    placeholder?: string;
};

export function DatePicker({ id, value, onChange, placeholder = 'Seleziona data' }: DatePickerProps) {
    const [open, setOpen] = useState(false);

    const date = value ? parse(value, 'yyyy-MM-dd', new Date()) : undefined;

    function handleSelect(selected: Date | undefined) {
        onChange(selected ? format(selected, 'yyyy-MM-dd') : '');
        setOpen(false);
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    variant="outline"
                    data-empty={!date}
                    className={cn(
                        'w-full justify-start text-left font-normal',
                        'data-[empty=true]:text-muted-foreground',
                    )}
                >
                    <CalendarIcon className="size-4" />
                    {date ? format(date, 'dd/MM/yyyy', { locale: it }) : <span>{placeholder}</span>}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    selected={date}
                    onSelect={handleSelect}
                    locale={it}
                    captionLayout="dropdown"
                />
            </PopoverContent>
        </Popover>
    );
}
