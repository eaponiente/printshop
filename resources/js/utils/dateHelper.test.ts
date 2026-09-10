// Force a non-Manila device timezone for this whole file, BEFORE dayjs (or
// anything that touches it) is imported. dayjs/Date read `process.env.TZ`
// lazily, but can cache the resolved offset on first use, so this must run
// before any other import in the file. This is deliberate: the regression
// covered below is invisible when the test runner's device timezone happens
// to already be Asia/Manila (+8) — every offset-less string still "just
// works" by coincidence. Running under UTC (which is Manila −8h) is enough
// to expose it.
process.env.TZ = 'UTC';

import { describe, expect, it } from 'vitest';
import { toManilaClock, toManilaTime } from './dateHelper';

describe('toManilaTime', () => {
    it('does not shift a date-only string in a non-Manila device timezone', () => {
        expect(toManilaTime('2026-09-07', 'YYYY-MM-DD')).toBe('2026-09-07');
    });

    it('renders an offset-less "Y-m-d H:i:s" datetime as Manila wall time (regression)', () => {
        // This is the bug: `AuditLog::serializeDate()` emits
        // "2026-09-07 08:00:00" with no timezone designator. Since the
        // backend's app timezone is Asia/Manila, that string already IS
        // 08:00 AM Manila — it must render as 08:00 AM regardless of the
        // viewer's device timezone, not be re-interpreted as a UTC instant.
        expect(toManilaTime('2026-09-07 08:00:00', 'hh:mm A')).toBe('08:00 AM');
    });

    it('converts a UTC "Z" ISO instant to Manila time', () => {
        expect(toManilaTime('2026-09-07T00:00:00.000000Z', 'hh:mm A')).toBe(
            '08:00 AM',
        );
    });

    it('converts an explicit-offset ISO instant to Manila time', () => {
        expect(toManilaTime('2026-09-07T08:00:00+08:00', 'hh:mm A')).toBe(
            '08:00 AM',
        );
    });

    it.each([null, undefined, ''])('returns "N/A" for %p', (value) => {
        expect(toManilaTime(value)).toBe('N/A');
    });
});

describe('toManilaClock', () => {
    it('formats as hh:mm A in Manila time', () => {
        expect(toManilaClock('2026-09-07T00:00:00.000000Z')).toBe('08:00 AM');
    });
});
