<?php

namespace App\Models\Payroll;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Payroll\Attendance\Enums\HolidayType;

class Holiday extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'type' => HolidayType::class,
            'recurring' => 'boolean',
        ];
    }

    public static function forDate(string $date): ?self
    {
        $calDate = Carbon::parse($date);

        return static::where(function ($query) use ($calDate, $date) {
            $query->where('date', $date);
            $query->orWhere(function ($q) use ($calDate) {
                $q->where('recurring', true)
                    ->whereMonth('date', $calDate->month)
                    ->whereDay('date', $calDate->day);
            });
        })->first();
    }

    public function isRestDay(): bool
    {
        return false;
    }
}
