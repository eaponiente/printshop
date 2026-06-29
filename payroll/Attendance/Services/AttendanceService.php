<?php

namespace Payroll\Attendance\Services;

use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Fine;
use App\Models\Payroll\Holiday;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\TimeLog;
use App\Services\Payroll\PayrollSettingService;
use Carbon\Carbon;
use Payroll\Attendance\Enums\HolidayType;

class AttendanceService
{
    public function processDailyAttendance(Employee $employee, string $date): AttendanceSheet
    {
        $dateObj = Carbon::parse($date)->startOfDay();
        $schedule = EmployeeSchedule::activeForDate($employee->id, $date);

        $restDays = $schedule?->rest_days ?? [];
        $isRestDay = $restDays !== [] && in_array($dateObj->dayOfWeek, $restDays);

        $punches = TimeLog::where('employee_id', $employee->id)
            ->whereDate('timestamp', $date)
            ->whereNull('duplicate_of')
            ->orderBy('timestamp')
            ->get();

        $lateMinutes = 0;
        $lateDeduction = 0;
        $undertimeMinutes = 0;
        $undertimeDeduction = 0;
        $overtimeMinutes = 0;
        $overtimePay = 0;
        $overtimeMultiplier = null;
        $hoursWorked = 0;
        $isPresent = false;
        $absenceType = null;
        $isMorningHalf = false;
        $isAfternoonHalf = false;
        $isIncomplete = false;
        $incompleteReason = null;

        $dailyRate = $employee->current_daily_rate ?? 0;
        $hourlyRate = $dailyRate / 8;

        $inPunch = $punches->firstWhere('type.value', 'in');
        $outPunch = $punches->firstWhere('type.value', 'out');
        $lunchOut = $punches->firstWhere('type.value', 'lunch_out');
        $lunchIn = $punches->firstWhere('type.value', 'lunch_in');
        $otInPunch = $punches->firstWhere('type.value', 'overtime_in');
        $otOutPunch = $punches->firstWhere('type.value', 'overtime_out');

        $hasAnyPunch = $punches->isNotEmpty();

        $configuredTail = $schedule?->unpaid_tail_minutes ?? 30;

        // Determine schedule times
        $defaultStart = '08:00';
        $defaultEnd = '17:00';
        $schedStartTime = $schedule?->start_time ? Carbon::parse($schedule->start_time)->format('H:i') : $defaultStart;
        $schedEndTime = $schedule?->end_time ? Carbon::parse($schedule->end_time)->format('H:i') : $defaultEnd;

        $scheduleStart = Carbon::parse("{$date} {$schedStartTime}");
        $scheduleEnd = Carbon::parse("{$date} {$schedEndTime}");

        // Unpaid tail only applies to schedules whose paid work would exceed 8h.
        // Excess over 480 paid minutes (assuming a 60-min lunch) caps the tail.
        $scheduledPaidMinutes = abs($scheduleStart->diffInMinutes($scheduleEnd)) - 60;
        $excessOver8h = max(0, $scheduledPaidMinutes - 480);
        $unpaidTailMinutes = min($configuredTail, $excessOver8h);
        $paidEndTime = $scheduleEnd->copy()->subMinutes($unpaidTailMinutes);

        if ($hasAnyPunch) {
            $isPresent = true;

            if (! $inPunch) {
                $isIncomplete = true;
                $incompleteReason = 'No punch-in recorded';
            } elseif (! $outPunch) {
                $isIncomplete = true;
                $incompleteReason = 'Punch-out missing';
            } elseif ($lunchOut && ! $lunchIn) {
                $isIncomplete = true;
                $incompleteReason = 'Lunch return punch missing';
            } elseif (! $lunchOut && $lunchIn) {
                $isIncomplete = true;
                $incompleteReason = 'Lunch break punch missing';
            } elseif ($otInPunch && ! $otOutPunch) {
                $isIncomplete = true;
                $incompleteReason = 'Overtime punch-out missing';
            } elseif (! $otInPunch && $otOutPunch) {
                $isIncomplete = true;
                $incompleteReason = 'Overtime punch-in missing';
            }

            if ($inPunch) {
                $rawInTime = Carbon::parse($inPunch->timestamp);

                // Half-day detection (must precede late calculation)
                $noonTime = Carbon::parse("{$date} 12:00");
                $isMorningHalf = $outPunch
                    && $rawInTime->lt($noonTime)
                    && Carbon::parse($outPunch->timestamp)->lt(Carbon::parse("{$date} 13:00"))
                    && ! $isRestDay;
                $isAfternoonHalf = $rawInTime->gte($noonTime)
                    && $scheduleStart->lt($noonTime)
                    && ! $isRestDay;

                // Tardy: based on actual punch vs schedule start (suppressed for afternoon half)
                if ($rawInTime->gt($scheduleStart) && ! $isAfternoonHalf) {
                    $lateMinutes = abs($rawInTime->diffInMinutes($scheduleStart));
                }

                $perMinute = (float) (app(PayrollSettingService::class)->get('late_deduction_per_minute', config('payroll.late_deduction_per_minute')));
                $threshold = (int) (app(PayrollSettingService::class)->get('late_deduction_threshold_minutes', config('payroll.late_deduction_threshold_minutes')));

                if ($lateMinutes <= $threshold) {
                    $lateDeduction = round($lateMinutes * $perMinute, 2);
                } else {
                    $lateDeduction = round(
                        ($threshold * $perMinute) + (($lateMinutes - $threshold) * ($hourlyRate / 60)),
                        2
                    );
                }

                // Cap early arrival: don't credit time before schedule start
                $inTime = $rawInTime->lt($scheduleStart) ? $scheduleStart->copy() : $rawInTime->copy();

                // Lunch computation
                $lunchDeductionMinutes = 0;
                if ($lunchOut && $lunchIn) {
                    $actualLunch = $lunchOut->timestamp->diffInMinutes($lunchIn->timestamp);
                    if ($actualLunch > 60) {
                        $lunchDeductionMinutes = $actualLunch - 60;
                    }
                } elseif (! $lunchOut || ! $lunchIn) {
                    $rawDuration = $outPunch
                        ? $outPunch->timestamp->diffInMinutes($inPunch->timestamp)
                        : 0;
                    if ($rawDuration >= 300) {
                        $wStart = $inPunch->timestamp->hour * 60 + $inPunch->timestamp->minute;
                        $wEnd = $outPunch
                            ? $outPunch->timestamp->hour * 60 + $outPunch->timestamp->minute
                            : $wStart + $rawDuration;
                        if ($wStart < 14 * 60 && $wEnd > 11 * 60) {
                            $lunchDeductionMinutes = 60;
                        }
                    }
                }

                // Undertime: early departure + over-lunch
                $undertimeMinutes = $lunchDeductionMinutes;
                if ($outPunch) {
                    $outTimeRaw = Carbon::parse($outPunch->timestamp);
                    if (! $isRestDay && $outTimeRaw->lt($paidEndTime)) {
                        $undertimeMinutes += abs($paidEndTime->diffInMinutes($outTimeRaw));
                    }
                }
                $undertimeDeduction = round(($undertimeMinutes / 60) * $hourlyRate, 2);

                // Half-day: charge only for the missing half, capped at paidEndTime.
                // Morning half uses scheduleStart (not actual punch) so late deduction
                // doesn't also inflate undertime. Afternoon half uses inTime because
                // the late penalty is already suppressed for that case.
                if (($isMorningHalf || $isAfternoonHalf) && $outPunch) {
                    $outTimeForHalf = Carbon::parse($outPunch->timestamp);
                    if ($outTimeForHalf->gt($paidEndTime)) {
                        $outTimeForHalf = $paidEndTime->copy();
                    }
                    $halfStartTime = $isMorningHalf ? $scheduleStart : $inTime;
                    $halfWorkedMinutes = $halfStartTime->diffInMinutes($outTimeForHalf);
                    $fullDayMinutes = $scheduledPaidMinutes - $unpaidTailMinutes;
                    $undertimeMinutes = max(0, $fullDayMinutes - $halfWorkedMinutes);
                    $undertimeDeduction = round(($undertimeMinutes / 60) * $hourlyRate, 2);
                }

                // Hours worked (uses capped inTime)
                $endTime = $outPunch ? Carbon::parse($outPunch->timestamp) : $paidEndTime;
                if (! $isRestDay && $endTime->gt($paidEndTime)) {
                    $endTime = $paidEndTime;
                }

                if ($lunchOut && $lunchIn) {
                    $morningEnd = $lunchOut->timestamp->lt($endTime)
                        ? Carbon::parse($lunchOut->timestamp)
                        : $endTime;
                    $morningMins = abs($inTime->diffInMinutes($morningEnd));
                    $afternoonMins = $lunchIn->timestamp->lt($endTime)
                        ? abs($lunchIn->timestamp->diffInMinutes($endTime))
                        : 0;
                    $hoursWorked = max(0, round(($morningMins + $afternoonMins) / 60, 2));
                } else {
                    $rawMinutes = abs($inTime->diffInMinutes($endTime)) - $lunchDeductionMinutes;
                    $hoursWorked = max(0, round($rawMinutes / 60, 2));
                }

                // Overtime — directly from OVERTIME_IN/OVERTIME_OUT punches (primary),
                // or an approved OvertimeRequest as fallback.
                $otMins = 0;
                $shiftType = 'regular_day';

                if ($otInPunch && $otOutPunch) {
                    $otMins = abs(
                        Carbon::parse($otInPunch->timestamp)->diffInMinutes(Carbon::parse($otOutPunch->timestamp))
                    );
                } else {
                    $otRequest = OvertimeRequest::where('employee_id', $employee->id)
                        ->where('date', $date)
                        ->where('status', 'approved')
                        ->first();

                    if ($otRequest) {
                        $otMins = $otRequest->getApprovedMinutes();
                        $shiftType = $otRequest->shift_type ?? 'regular_day';
                    }
                }

                if (! $isRestDay && $otMins > 0) {
                    $overtimeMinutes = $otMins;
                    $multiplier = $this->getOTMultiplier($shiftType);
                    $overtimeMultiplier = $multiplier;
                    $overtimePay = round(($otMins / 60) * $hourlyRate * $multiplier, 2);
                } elseif ($isRestDay && $outPunch) {
                    // Working on rest day — count the in-to-out span (minus lunch) as OT-rate hours
                    $restDayMinutes = abs($inTime->diffInMinutes($outPunch->timestamp));
                    if ($lunchOut && $lunchIn) {
                        $lunchTaken = abs($lunchOut->timestamp->diffInMinutes($lunchIn->timestamp));
                        $restDayMinutes -= min($lunchTaken, 60);
                    } else {
                        $restDayMinutes -= 60;
                    }
                    if ($restDayMinutes > 0) {
                        $multiplier = $this->getOTMultiplier('rest_day');
                        $overtimeMinutes = $restDayMinutes;
                        $overtimeMultiplier = $multiplier;
                        $hoursWorked = round($restDayMinutes / 60, 2);
                        $overtimePay = 0;
                    }
                }
            }
        } elseif (! $hasAnyPunch && ! $isRestDay) {
            $absenceType = 'unexcused';
        }

        // Holiday check
        $holiday = Holiday::forDate($date);
        $holidayType = null;
        $holidayPayPercent = null;
        $holidayPay = 0;

        if ($holiday) {
            // Holiday on Sunday = no holiday pay for anyone
            if ($dateObj->dayOfWeek === 0) {
                // Intentionally skip: no holiday pay when holiday falls on Sunday
            } else {
                $holidayType = $holiday->type->value;
                if ($holiday->type === HolidayType::REGULAR) {
                    if ($isPresent) {
                        $holidayPayPercent = $isRestDay ? 260 : 200;
                    } else {
                        $holidayPayPercent = 0;
                    }
                } elseif ($holiday->type === HolidayType::SPECIAL) {
                    $holidayPayPercent = $isPresent ? ($isRestDay ? 150 : 130) : 0;
                }

                if ($holidayPayPercent > 0) {
                    if ($isPresent) {
                        $basePayPercent = $isRestDay ? 130 : 100;
                        $holidayPay = round($dailyRate * ($holidayPayPercent - $basePayPercent) / 100, 2);
                    } else {
                        $holidayPay = round($dailyRate * $holidayPayPercent / 100, 2);
                    }
                }
            }
        }

        // Leave check
        $leaveRequest = LeaveRequest::where('employee_id', $employee->id)
            ->where('date', $date)
            ->where('status', 'approved')
            ->first();

        $leaveType = null;
        $leaveDuration = null;
        $leaveIsPaid = false;
        $leaveHoursCredited = 0;

        $hasFullDayLeave = $leaveRequest && $leaveRequest->duration === 'full_day';

        if ($hasFullDayLeave) {
            $leaveType = $leaveRequest->leave_type;
            $leaveDuration = 'full_day';
            $leaveIsPaid = $leaveRequest->is_paid;
            $leaveHoursCredited = 8;

            $isPresent = true;
            $absenceType = 'approved_leave';
            $hoursWorked = 0;
            $lateMinutes = 0;
            $lateDeduction = 0;
            $undertimeMinutes = 0;
            $undertimeDeduction = 0;
            $overtimeMinutes = 0;
            $overtimePay = 0;
            $overtimeMultiplier = null;

            $holidayType = null;
            $holidayPayPercent = null;
            $holidayPay = 0;
        }

        // Daily wage
        $fineDeduction = Fine::where('employee_id', $employee->id)->where('date', $date)->sum('amount');

        // Break fine when employee completed their day (punched out) but skipped lunch punches.
        // Skipped when punch-out is absent — day is still in progress, lunch may come later.
        if ($isPresent && $outPunch && ! $isRestDay && ! $hasFullDayLeave && (! $lunchOut || ! $lunchIn) && ! $isMorningHalf && ! $isAfternoonHalf) {
            $noBreakFine = (float) app(PayrollSettingService::class)->get('no_break_fine', config('payroll.no_break_fine'));
            $fineDeduction += $noBreakFine;
        }

        if ($hasFullDayLeave) {
            $basePay = $leaveIsPaid ? $dailyRate : 0;
            $dailyWage = round($basePay - $fineDeduction, 2);
            if ($dailyWage < 0) {
                $dailyWage = 0;
            }
        } else {
            if ($isRestDay && $isPresent) {
                $basePay = round($hoursWorked * $hourlyRate * 1.30, 2);
            } else {
                $basePay = $isPresent ? $dailyRate : 0;
            }
            $dailyWage = round($basePay - $lateDeduction - $undertimeDeduction - $fineDeduction + $overtimePay + $holidayPay, 2);
            if ($dailyWage < 0) {
                $dailyWage = 0;
            }
        }

        $sheet = AttendanceSheet::where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        if ($sheet && $sheet->isLocked()) {
            return $sheet;
        }

        $data = [
            'employee_id' => $employee->id,
            'date' => $date,
            'schedule_start_time' => $schedStartTime,
            'schedule_end_time' => $schedEndTime,
            'rest_days' => $schedule?->rest_days,
            'daily_rate' => $dailyRate,
            'late_minutes' => $lateMinutes,
            'late_deduction' => $lateDeduction,
            'undertime_minutes' => $undertimeMinutes,
            'undertime_deduction' => $undertimeDeduction,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_pay' => $overtimePay,
            'overtime_multiplier' => $overtimeMultiplier,
            'holiday_type' => $holidayType,
            'holiday_pay_percent' => $holidayPayPercent,
            'holiday_pay' => $holidayPay,
            'fine_deduction' => $fineDeduction,
            'hours_worked' => $hoursWorked,
            'daily_wage' => $dailyWage,
            'is_present' => $isPresent,
            'absence_type' => $absenceType,
            'is_rest_day' => $isRestDay,
            'is_incomplete' => $isIncomplete,
            'incomplete_reason' => $incompleteReason,
            'leave_type' => $leaveType,
            'leave_duration' => $leaveDuration,
            'leave_is_paid' => $leaveIsPaid,
            'leave_hours_credited' => $leaveHoursCredited,
        ];

        if ($sheet) {
            $sheet->update($data);
        } else {
            $sheet = AttendanceSheet::create($data);
        }

        return $sheet;
    }

    protected function getOTMultiplier(string $shiftType): float
    {
        return match ($shiftType) {
            'regular_day' => 1.25,
            'rest_day', 'special_holiday' => 1.69,
            'rest_day_plus_special' => 1.95,
            'regular_holiday' => 2.60,
            'rest_day_plus_regular' => 3.38,
            default => 1.25,
        };
    }
}
