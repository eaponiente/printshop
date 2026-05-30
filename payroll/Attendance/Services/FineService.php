<?php

namespace Payroll\Attendance\Services;

use App\Models\Payroll\Employee;
use App\Models\Payroll\Fine;

class FineService
{
    public function __construct(
        private AttendanceService $attendanceService,
    ) {}

    public function mark(Employee $employee, string $date, string $fineType, float $amount, ?string $note): Fine
    {
        $fine = Fine::create([
            'employee_id' => $employee->id,
            'date' => $date,
            'fine_type' => $fineType,
            'amount' => $amount,
            'note' => $note,
            'marked_by' => auth()->id(),
        ]);

        $this->attendanceService->processDailyAttendance($employee, $date);

        return $fine;
    }

    public function remove(Fine $fine): void
    {
        $employee = $fine->employee;
        $date = $fine->date->format('Y-m-d');
        $fine->delete();

        $this->attendanceService->processDailyAttendance($employee, $date);
    }
}
