<?php

namespace Database\Seeders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        // Create 20 Purchase Orders
        PurchaseOrder::factory(20)->create()->each(function ($po) {

            $details = PurchaseOrderDetail::factory(rand(2, 5))->make([
                'purchase_order_id' => $po->id,
            ]);

            // Save the details to the database
            $po->details()->saveMany($details);

            // Update the grand_total based on the sum of total_price from details
            $po->update([
                'grand_total' => $po->details()->sum(DB::raw('quantity * unit_price'))
            ]);
        });
    }
}
