import { CalendarIcon } from 'lucide-react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { toManilaTime } from '@/utils/dateHelper';

type DateRangePickerProps = {
    startDate: string;
    endDate: string;
    onChange: (startDate: string, endDate: string) => void;
};

/**
 * Format a calendar-selected Date as YYYY-MM-DD using its *local* calendar
 * fields. react-day-picker yields dates at local midnight, so `toISOString()`
 * would shift them to the previous day in any positive-offset timezone (e.g.
 * Asia/Manila, UTC+8) — picking May 25 would submit May 24.
 */
const toLocalISODate = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

export function DateRangePicker({
    startDate,
    endDate,
    onChange,
}: DateRangePickerProps) {
    const range: DateRange = {
        from: new Date(`${startDate}T00:00:00`),
        to: new Date(`${endDate}T00:00:00`),
    };

    const handleSelect = (selected: DateRange | undefined) => {
        if (!selected?.from || !selected?.to) {
            return;
        }

        onChange(toLocalISODate(selected.from), toLocalISODate(selected.to));
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className="justify-start text-left font-normal"
                >
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {toManilaTime(startDate)} – {toManilaTime(endDate)}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="range"
                    selected={range}
                    onSelect={handleSelect}
                    numberOfMonths={2}
                    defaultMonth={range.from}
                />
            </PopoverContent>
        </Popover>
    );
}
