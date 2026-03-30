<?php

namespace Database\Seeders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->whereNot('role', 'superadmin')->get();

        // Create 20 Purchase Orders
        PurchaseOrder::factory(20)->create()->each(function ($po, $users) {

            $user = $users->random();

            $details = PurchaseOrderDetail::factory(rand(2, 5))->make([
                'purchase_order_id' => $po->id,
            ]);

            // Save the details to the database
            $po->details()->saveMany($details);

            // Update the grand_total based on the sum of total_price from details
            $po->update([
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'grand_total' => $po->details()->sum(DB::raw('quantity * unit_price'))
            ]);
        });
    }
}
