<?php

use App\Models\Payroll\CorrectionRequest;
use App\Models\Payroll\CorrectionRequestItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CorrectionRequest::whereNotNull('requested_time')
            ->chunkById(100, function ($requests) {
                foreach ($requests as $request) {
                    $time = $request->requested_time;

                    match ($request->correction_type) {
                        'missed_punch_in' => CorrectionRequestItem::create([
                            'correction_request_id' => $request->id,
                            'punch_type' => 'in',
                            'requested_time' => $time,
                        ]),
                        'missed_punch_out' => CorrectionRequestItem::create([
                            'correction_request_id' => $request->id,
                            'punch_type' => 'out',
                            'requested_time' => $time,
                        ]),
                        'time_adjustment', 'absent_to_present' => (function () use ($request, $time) {
                            CorrectionRequestItem::create([
                                'correction_request_id' => $request->id,
                                'punch_type' => 'in',
                                'requested_time' => $time,
                            ]);
                            CorrectionRequestItem::create([
                                'correction_request_id' => $request->id,
                                'punch_type' => 'out',
                                'requested_time' => $time->copy()->addHours(9),
                            ]);
                        })(),
                        default => null,
                    };
                }
            });
    }

    public function down(): void
    {
        // Data migration is not safely reversible
    }
};
