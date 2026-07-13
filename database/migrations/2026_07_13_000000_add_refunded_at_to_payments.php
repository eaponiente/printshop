<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('staff_id');
            // Supports Payment::scopeLive() (refunded_at IS NULL) and
            // per-transaction refund lookups without a correlated subquery.
            $table->index(['transaction_id', 'refunded_at']);
        });

        // Backfill: stamp every positive row that a later refund already
        // reversed, using the same id-heuristic the previous scopeLive used,
        // so the new "refunded_at IS NULL" predicate reproduces today's
        // behaviour exactly. Each reversed row is dated to the refund that
        // reversed it (the earliest negative row with a greater id).
        //
        // Done in PHP rather than a single UPDATE...SELECT because MySQL
        // (error 1093) forbids a subquery selecting from the table being
        // updated; this query-builder form runs identically on MySQL/SQLite.
        DB::table('payments')
            ->where('amount', '<', 0)
            ->distinct()
            ->pluck('transaction_id')
            ->each(function ($transactionId) {
                $rows = DB::table('payments')
                    ->where('transaction_id', $transactionId)
                    ->orderBy('id')
                    ->get(['id', 'amount', 'created_at']);

                foreach ($rows as $row) {
                    if ($row->amount <= 0) {
                        continue;
                    }

                    $reversingRefund = $rows->first(fn ($r) => $r->amount < 0 && $r->id > $row->id);

                    if ($reversingRefund) {
                        DB::table('payments')
                            ->where('id', $row->id)
                            ->update(['refunded_at' => $reversingRefund->created_at]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['transaction_id', 'refunded_at']);
            $table->dropColumn('refunded_at');
        });
    }
};
