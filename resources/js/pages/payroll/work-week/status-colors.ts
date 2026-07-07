export const STATUS_COLORS = {
    present: 'border-green-200 bg-green-50 text-green-700',
    late: 'text-yellow-700',
    absent: 'border-red-200 bg-red-50 text-red-700',
    holiday: 'border-blue-200 bg-blue-50 text-blue-700',
    leave: 'border-purple-200 bg-purple-50 text-purple-700',
    rest: 'border-blue-100 bg-blue-50/40 text-blue-400',
    empty: 'border-transparent text-gray-400',
} as const;

export const STATUS_GLYPHS = {
    present: '✔',
    absent: 'A',
    holiday: 'H',
    leave: 'Leave',
    rest: 'Rest',
    empty: '—',
} as const;
