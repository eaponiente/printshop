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

        $dailyRate = $employee->current_daily_rate ?? 0;
        $hourlyRate = $dailyRate / 8;

        $inPunch = $punches->firstWhere('type.value', 'in');
        $outPunch = $punches->firstWhere('type.value', 'out');
        $lunchOut = $punches->firstWhere('type.value', 'lunch_out');
        $lunchIn = $punches->firstWhere('type.value', 'lunch_in');

        $hasAnyPunch = $punches->isNotEmpty();

        $unpaidTailMinutes = $schedule?->unpaid_tail_minutes ?? 30;

        // Determine schedule times
        $defaultStart = '08:00';
        $defaultEnd = '17:00';
        $schedStartTime = $schedule?->start_time ? Carbon::parse($schedule->start_time)->format('H:i') : $defaultStart;
        $schedEndTime = $schedule?->end_time ? Carbon::parse($schedule->end_time)->format('H:i') : $defaultEnd;

        $scheduleStart = Carbon::parse("{$date} {$schedStartTime}");
        $scheduleEnd = Carbon::parse("{$date} {$schedEndTime}");
        $paidEndTime = $scheduleEnd->copy()->subMinutes($unpaidTailMinutes);

        if ($hasAnyPunch) {
            $isPresent = true;

            if ($inPunch) {
                $rawInTime = Carbon::parse($inPunch->timestamp);

                // Tardy: based on actual punch vs schedule start
                if ($rawInTime->gt($scheduleStart)) {
                    $lateMinutes = abs($rawInTime->diffInMinutes($scheduleStart));
                }

                $lateDeduction = $lateMinutes <= 20
                    ? round($lateMinutes * 5, 2)
                    : round((20 * 5) + (($lateMinutes - 20) * ($hourlyRate / 60)), 2);

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

                // Overtime — threshold at full shift duration (480 + unpaid tail)
                $totalWorkMinutes = 0;
                if ($outPunch) {
                    $totalWorkMinutes = abs($inTime->diffInMinutes($outPunch->timestamp));
                    if ($lunchOut && $lunchIn) {
                        $lunchTaken = abs($lunchOut->timestamp->diffInMinutes($lunchIn->timestamp));
                        $totalWorkMinutes -= min($lunchTaken, 60);
                    } else {
                        $totalWorkMinutes -= 60;
                    }
                }

                $otThreshold = 480 + $unpaidTailMinutes;

                if ($totalWorkMinutes > $otThreshold && ! $isRestDay) {
                    $otMins = $totalWorkMinutes - $otThreshold;
                    $otRequest = OvertimeRequest::where('employee_id', $employee->id)
                        ->where('date', $date)
                        ->where('status', 'approved')
                        ->first();

                    if ($otRequest) {
                        $approvedMins = $otRequest->getApprovedMinutes();
                        $otMins = min($otMins, $approvedMins);
                    } else {
                        $otMins = 0;
                    }

                    if ($otMins >= 60) {
                        $overtimeMinutes = $otMins;
                        $multiplier = $this->getOTMultiplier($otRequest?->shift_type ?? 'regular_day');
                        $overtimeMultiplier = $multiplier;
                        $overtimePay = round(($otMins / 60) * $hourlyRate * $multiplier, 2);
                    }
                } elseif ($isRestDay && $totalWorkMinutes > 0) {
                    // Working on rest day
                    $multiplier = $this->getOTMultiplier('rest_day');
                    $overtimeMinutes = $totalWorkMinutes;
                    $overtimeMultiplier = $multiplier;
                    $hoursWorked = round($totalWorkMinutes / 60, 2);
                    $overtimePay = 0;
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
                        $prevWorkDate = $this->findLastWorkingDay($employee, $date);
                        $prevSheet = $prevWorkDate
                            ? AttendanceSheet::where('employee_id', $employee->id)->where('date', $prevWorkDate)->first()
                            : null;
                        if ($prevSheet && ($prevSheet->is_present || $prevSheet->absence_type === 'approved_leave')) {
                            $holidayPayPercent = 100;
                        } else {
                            $holidayPayPercent = 0;
                        }
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

        $hasFullDayLeave = $leaveRequest && $leaveRequest->duration === 'full';

        if ($hasFullDayLeave) {
            $leaveType = $leaveRequest->leave_type;
            $leaveDuration = 'full';
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

        if ($hasFullDayLeave) {
            $basePay = $leaveIsPaid ? $dailyRate : 0;
            $dailyWage = round($basePay - $fineDeduction, 2);
            if ($dailyWage < 0) {
                $dailyWage = 0;
            }
        } else {
            $netWorkMinutes = round($hoursWorked * 60);

            if (! $isRestDay) {
                $hasApprovedOT = OvertimeRequest::where('employee_id', $employee->id)
                    ->where('date', $date)
                    ->where('status', 'approved')
                    ->exists();

                if ($hasApprovedOT) {
                    $regularPaidMinutes = $netWorkMinutes;
                } else {
                    $maxPaidMinutes = max(480 - $lateMinutes, 240);
                    $regularPaidMinutes = min($netWorkMinutes, $maxPaidMinutes);
                }
            } else {
                $regularPaidMinutes = $netWorkMinutes;
            }

            $basePaidHours = $regularPaidMinutes / 60;

            $basePay = $isPresent ? round($basePaidHours * $hourlyRate, 2) : 0;
            if ($isRestDay && $isPresent) {
                $basePay = round($hoursWorked * $hourlyRate * 1.30, 2);
            }
            $dailyWage = round($basePay - $undertimeDeduction - $fineDeduction + $overtimePay + $holidayPay, 2);
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

    protected function findLastWorkingDay(Employee $employee, string $date): ?string
    {
        $current = Carbon::parse($date)->subDay();

        for ($i = 0; $i < 14; $i++) {
            $dateStr = $current->toDateString();
            $dayOfWeek = $current->dayOfWeek;

            $schedule = EmployeeSchedule::activeForDate($employee->id, $dateStr);
            $restDays = $schedule?->rest_days ?? [];

            $isHoliday = Holiday::forDate($dateStr) !== null;
            $isSunday = $dayOfWeek === 0;
            $isRestDay = in_array($dayOfWeek, $restDays);

            if (! $isHoliday && ! $isSunday && ! $isRestDay) {
                return $dateStr;
            }

            $current->subDay();
        }

        return null;
    }
}
