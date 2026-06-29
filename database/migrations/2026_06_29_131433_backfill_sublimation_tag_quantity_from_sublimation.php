<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For each sublimation with tags, copy the top-level quantity onto the
        // first pivot row (lowest id). Other pivot rows for that sublimation
        // keep quantity = 1 (the add-column migration default).
        DB::table('sublimations')
            ->where('quantity', '>', 0)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('sublimation_tag')
                ->whereColumn('sublimation_tag.sublimation_id', 'sublimations.id')
            )
            ->select(['id', 'quantity'])
            ->get()
            ->each(function ($sublimation) {
                $firstPivotId = DB::table('sublimation_tag')
                    ->where('sublimation_id', $sublimation->id)
                    ->min('id');

                if ($firstPivotId) {
                    DB::table('sublimation_tag')
                        ->where('id', $firstPivotId)
                        ->update(['quantity' => $sublimation->quantity]);
                }
            });
    }

    public function down(): void
    {
        DB::table('sublimation_tag')->update(['quantity' => 1]);
    }
};
